<?php

namespace App\Processors;

use App\Misc\MetaData;
use App\Utils\DateTimeUtil;
use App\Utils\MiscUtil;
use App\Utils\PullRequestUtil;
use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Misc\Logger;
use App\DataFetcher\Requesters\GraphQLRequester;
use stdClass;

class RecentMergedPrsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'merged-prs';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function getHtmlTableScript(): string
    {
        return <<<EOT
            (function() {
                // sort by mergedAt desc
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'mergedAt') {
                            window.clearInterval(interval);
                            // click twice to sort desc
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
        $modules = Consts::MODULES;

        $rows = [];

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo, $moduleType];
                }
            }
        }

        // $varsList = [
        //     ['silverstripe', 'silverstripe-framework', 'regular'],
        // ];

        // TODO: this should be somehow wrapped into GraphQLRequester (see comment in OpenPrsProcessor)
        $keys = [];
        $maxAttempts = 3;
        $threeWeeksAgo = date('Y-m-d', strtotime('now - 1 year'));
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            foreach ($varsList as $vars) {
                list($account, $repo, $moduleType) = $vars;
                $postBody = $this->buildMergedPRsQuery($account, $repo, $threeWeeksAgo);
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
                    $row = $this->deriveMergedPrDataRow($pr, $account, $repo);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
                $keys[$key] = true;
            }
        }
        return $rows;
    }

    private function buildMergedPRsQuery(string $account, string $repo, string $mergedSince)
    {
        return <<<EOT
        {
            search(
                query: "repo:{$account}/{$repo} is:merged is:pr merged:>={$mergedSince}"
                type: ISSUE
                last: 100
            ) {
                nodes {
                    ... on PullRequest {
                        title
                        url
                        createdAt
                        author {
                            login
                            ... on User {
                                name
                            }
                        }
                        mergedAt
                        mergedBy {
                            login
                            ... on User {
                                name
                            }
                        }
                        files(first: 100) {
                            nodes {
                                path
                                additions
                                deletions
                            }
                        }
                    }
                }
            }
        }
        EOT;
    }

    private function deriveMergedPrDataRow(stdClass $pr, string $account, string $repo): array
    {
        // Used to check the type of PR
        $files = [];
        foreach ($pr->files->nodes as $node) {
            $files[] = [
                'path' => $node->path,
                'additions' => $node->additions,
                'deletions' => $node->deletions,
            ];
        }

        // author very occasionally null, possibly user deleted from github.
        // could theoretically happen with other user reference too.
        $author = $pr->author->login ?? '';
        $authorName = $pr->author->name ?? '';
        $mergedBy = $pr->mergedBy->login ?? '';
        $mergedByName = $pr->mergedBy->name ?? '';

        $prStats = $this->prStats($pr->files->nodes);

        $row = [
            'account' => $account,
            'repo' => $repo,
            'title' => $pr->title,
            'type' => PullRequestUtil::getPullRequestType($files, $author),
            'author' => $author,
            'authorName' => $authorName,
            'authorType' => MiscUtil::deriveUserType($author, MetaData::TEAMS),
            'mergedBy' => $mergedBy,
            'mergedByName' => $mergedByName,
            'mergedByType' => MiscUtil::deriveUserType($mergedBy, MetaData::TEAMS),
            'url' => $pr->url,
            'createdAt' => DateTimeUtil::timestampToNZDate($pr->createdAt),
            'mergedAt' => DateTimeUtil::timestampToNZDate($pr->mergedAt),
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
