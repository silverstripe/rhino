<?php

namespace App\Processors;

use App\Misc\MetaData;
use App\Utils\DateTimeUtil;
use App\Utils\MiscUtil;
use App\Utils\PullRequestUtil;
use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Logger;
use App\DataFetcher\Requesters\GraphQLRequester;
use stdClass;
use App\Misc\SupportedModulesManager;

class OpenPrsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'open-prs';
    }

    public function getSortOrder(): int
    {
        return 3;
    }

    public function getHtmlTableScript(): string
    {
        return <<<EOT
            (function() {
                // sort by updatedAtNZ desc
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'updatedAt') {
                            window.clearInterval(interval);
                            // click twice to sort descupdatedAtNZ
                            th.click();
                            th.click();
                            return;
                        }
                    }
                }, 250);
            })();
EOT;
    }

    public function process(bool $refetch): array
    {
        $apiConfig = new GitHubApiConfig();
        $requester = new GraphQLRequester($apiConfig);
        $manager = new SupportedModulesManager();
        $modules = $manager->getModules();

        $rows = [];

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo, $moduleType];
                }
            }
        }

        // can often be quite a few errors with github graphql api
        // partcularly when doing a new query for the first time
        // try downloading a few times in total, though retry after all other queries have been done
        $keys = [];
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            foreach ($varsList as $vars) {
                list($account, $repo, $moduleType) = $vars;
                $postBody = $this->buildOpenPRsQuery($account, $repo);
                $hash = md5($postBody);
                $key = "{$account}-{$repo}-{$hash}";
                if (array_key_exists($key, $keys)) {
                    continue;
                }
                if ($attempt > 1) {
                    Logger::singleton()->log("Retry attempt {$attempt} of {$maxAttempts}");
                }
                $json = $requester->fetch('/graphql', $postBody, $account, $repo, $refetch);
                foreach ($json->root->data->search->nodes ?? [] as $pr) {
                    $row = $this->deriveOpenPRDataRow($pr, $moduleType, $account, $repo);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
                $keys[$key] = true;
            }
        }
        return $rows;
    }

    private function buildOpenPRsQuery(string $account, string $repo)
    {
        return <<<EOT
        {
            search(
                query: "repo:{$account}/{$repo} is:open is:pr archived:false"
                type: ISSUE
                last: 100
            ) {
                nodes {
                    ... on PullRequest {
                        title
                        body
                        url
                        author {
                            login
                        }
                        isDraft
                        createdAt
                        updatedAt
                        closedAt
                        comments(last: 10) {
                            nodes {
                                author {
                                    login
                                }
                                createdAt
                            }
                        }
                        merged
                        mergedAt
                        mergedBy {
                            login
                        }
                        files(first: 100) {
                            nodes {
                                path
                                additions
                                deletions
                            }
                        }
                        reviews(last: 10) {
                            nodes {
                                state
                                author {
                                    login
                                }
                            }
                        }
                        mergeable
                        commits(last: 1) {
                            nodes {
                                commit {
                                    committedDate
                                    status {
                                        state
                                        contexts {
                                            state
                                            context
                                            description
                                            targetUrl
                                        }
                                    }
                                }
                            }
                        }
                        labels(last: 10) {
                            nodes {
                                name
                            }
                        }
                    }
                }
            }
        }
EOT;
    }

    private function deriveOpenPRDataRow(stdClass $pr, string $moduleType, string $account, string $repo): array
    {
        $files = [];
        foreach ($pr->files->nodes as $node) {
            $files[] = [
                'path' => $node->path,
                'additions' => $node->additions,
                'deletions' => $node->deletions,
            ];
        }

        // author very occasionally null, possibly user deleted from github
        $author = $pr->author->login ?? '';

        // ci tool red
        $ciToolRed = [
            'scrutinizer' => false,
            'codecov' => false,
        ];
        foreach ($pr->commits->nodes[0]->commit->status->contexts ?? [] as $context) {
            foreach (array_keys($ciToolRed) as $ci) {
                // states are SUCCESS, ERROR, FAILURE, PENDING
                // it's OK to treat PENDING as red, as sometimes ci tool gets stuck in a
                // pending state, and 'pending' is not immediately actionable anyway
                if (strpos(strtolower($context->context), $ci) !== false &&
                    $context->state != 'SUCCESS'
                ) {
                    $ciToolRed[$ci] = true;
                }
            }
        }

        // merge conflicts
        $mrgConflicts = $pr->mergeable == 'CONFLICTING';

        // approved / change requeted
        $a = [];
        foreach ($pr->reviews->nodes as $review) {
            if (in_array($review->state, ['APPROVED', 'CHANGES_REQUESTED'])) {
                $a[$review->author->login ?? ''] = $review->state;
            }
        }
        $approved = !empty(array_filter($a, function ($v) {
            return $v == 'APPROVED';
        }));
        $changesReq = !empty(array_filter($a, function ($v) {
            return $v == 'CHANGES_REQUESTED';
        }));

        // last commit at
        // handle strange state with zero commits after force pushing
        // only happens on old closed branches
        $lastCommitAt = $pr->updatedAt;
        foreach ($pr->commits->nodes as $commit) {
            $lastCommitAt = $commit->commit->committedDate;
        }

        // ask to close
        $authorType = MiscUtil::deriveUserType($author, MetaData::TEAMS);

        $prStats = $this->prStats($pr->files->nodes);

        $row = [
            'account' => $account,
            'repo' => $repo,
            'title' => $pr->title,
            'type' => PullRequestUtil::getPullRequestType($files, $author),
            'author' => substr($author, 0, 10),
            'authorType' => $authorType,
            'url' => $pr->url,
            'createdAt' => DateTimeUtil::timestampToNZDate($pr->createdAt),
            'updatedAt' => DateTimeUtil::timestampToNZDate($pr->updatedAt),
            'lastCmitAt' => DateTimeUtil::timestampToNZDate($lastCommitAt),
            'approved' => $approved,
            'draft' => $pr->isDraft,
            'changesReq' => $changesReq,
            'mrgConflct' => $mrgConflicts,
            'numFiles' => $prStats['numFiles'],
            'linesAdded' => $prStats['linesAdded'],
            'linesRemoved' => $prStats['linesRemoved'],
            'fileTypes' => $prStats['fileTypes'],
            'docs' => $prStats['docs'],
            'unit' => $prStats['unit'],
            'behat' => $prStats['behat'],
            'jest' => $prStats['jest'],
        ];
        return $row;
    }
}
