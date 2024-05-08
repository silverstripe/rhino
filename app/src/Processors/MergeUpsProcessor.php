<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Requesters\RestRequester;
use SilverStripe\SupportedModules\BranchLogic;
use SilverStripe\SupportedModules\MetaData;
use App\Misc\SupportedModulesManager;

class MergeUpsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'merge-ups';
    }

    public function getSortOrder(): int
    {
        return 4;
    }

    public function getHtmlTableScript(): string
    {
        return <<<EOT
            (function() {
                // colour cells by inner-text value
                var tds = document.getElementsByTagName('td');
                for (var i = 0; i < tds.length; i++) {
                    var td = tds[i];
                    var v = td.innerHTML;
                    var c = '';
                    if (v.indexOf('needs-merge-up') != -1) {
                        c = 'khaki';
                    } else if (v.indexOf('up-to-date') != -1) {
                        c = 'palegreen';
                    }
                    if (c) {
                        td.style.background = c;
                    }
                    // make nothing sort last
                    if (['nothing'].indexOf(v) != -1) {
                        td.innerHTML = '<span style="display:none">zzz</span>';
                    }
                }
                // sort by Workflow status
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'Workflow status') {
                            window.clearInterval(interval);
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
        $requester = new RestRequester($apiConfig);
        $manager = new SupportedModulesManager();
        $modules = $manager->getModules();

        $repoList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $repoList[] = [$account, $repo];
                }
            }
        }

        $rows = [];
        foreach ($repoList as $githubRepository) {
            list($account, $repo) = $githubRepository;

            // get branches
            $json = $requester->fetch("/repos/$account/$repo?paginate=0", '', $account, $repo, $refetch);
            $defaultBranch = $json->root->default_branch;
            $repoData = MetaData::getMetaDataForRepository("$account/$repo");
            $composerJson = $requester->fetchFile($account, $repo, $defaultBranch, 'composer.json', $refetch);
            $branchesJson = $requester->fetch("/repos/$account/$repo/branches", '', $account, $repo, $refetch)->root ?? [];
            $allRepoBranches = array_map(fn($x) => $x->name, $branchesJson);
            $tagsJson = $requester->fetch("/repos/$account/$repo/tags", '', $account, $repo, $refetch)->root ?? [];
            $allRepoTags = array_map(fn($x) => $x->name, $tagsJson);
            $branches = BranchLogic::getBranchesForMergeUp("$account/$repo", $repoData, $defaultBranch, $allRepoTags, $allRepoBranches, $composerJson);
            if (empty($branches)) {
                continue;
            }
            $branches = array_reverse($branches);
            $majorBranches = array_values(array_filter($branches, fn ($branch) => ctype_digit((string) $branch)));

            $row = [
                'account' => $account,
                'repo' => $repo,
                'Workflow status' => '',
            ];

            // Check the highest major release represented in the branches, so we can display branches in the correct columns.
            // If the default branch isn't the highest major branch for this repo, get the correct composer.json content.
            if ($majorBranches[0] > $defaultBranch) {
                $composerJson = $requester->fetchFile($account, $repo, $majorBranches[0], 'composer.json', $refetch);
            }
            $highestMajorForRepo = (int) BranchLogic::getCmsMajor($repoData, $majorBranches[0], $composerJson) ?: MetaData::HIGHEST_STABLE_CMS_MAJOR;

            $nothingToMerge = 'nothing';
            // 'nm' = next major,
            // 'xnm' = cross next major
            // 'cm' = current major
            // 'xm' = cross major
            // 'pm' = prev major
            $n = 0;
            $toMajor = null;
            $branchIndex = 0;

            $nextMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR + 1;
            $currentMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
            $previousMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR - 1;

            foreach ([$nextMajor, "$currentMajor to $nextMajor", $currentMajor, "$previousMajor to $currentMajor", $previousMajor] as $type) {
                if (is_numeric($type)) {
                    $toMajor = $type;
                    $colPrefix = "CMS $type";
                    $columns = [];
                    if ($type > MetaData::HIGHEST_STABLE_CMS_MAJOR) {
                        $columns[] = $colPrefix;
                    } else {
                        $columns = [
                            $colPrefix . ' NextMin Branch',
                            $colPrefix . ' ToNextMin status',
                            $colPrefix . ' NextPat Branch',
                            $colPrefix . ' FromPrvMin status',
                            $colPrefix . ' PrvMin Branch',
                        ];
                    }
                    // Skip if the repo doesn't have a branch for this major release line.
                    if ($highestMajorForRepo < $type) {
                        foreach ($columns as $col) {
                            $row[$col] = str_ends_with($col, 'status') ? $nothingToMerge : '';
                        }
                        continue;
                    }
                    // Add actual column data here
                    foreach ($columns as $col) {
                        // If we have no branches left, or the branch is the wrong type for this column, skip the column
                        if (!isset($branches[$branchIndex]) || $col !== $colPrefix && !str_contains($col, 'NextMin') && !preg_match('/[0-9]+\.[0-9]/', $branches[$branchIndex])) {
                            $row[$col] = str_ends_with($col, 'status') ? $nothingToMerge : '';
                            continue;
                        }
                        if (str_ends_with($col, 'status')) {
                            $mergeFrom = $branches[$branchIndex];
                            $mergeInto = $branches[$branchIndex - 1];
                            $row[$col] = $this->getMergeUpDetail($account, $repo, $mergeFrom, $mergeInto, $requester, $refetch);
                        } else {
                            $row[$col] = $branches[$branchIndex] . '.x-dev';
                            $branchIndex++;
                        }
                    }
                } else {
                    $column = "{$type} status";
                    $row[$this->getSeparatorColumn($n)] = '';
                    if ($highestMajorForRepo < $toMajor || !isset($branches[$branchIndex])) {
                        $row[$column] = $nothingToMerge;
                    } else {
                        $mergeFrom = $branches[$branchIndex];
                        $mergeInto = $branches[$branchIndex - 1];
                        $row[$column] = $this->getMergeUpDetail($account, $repo, $mergeFrom, $mergeInto, $requester, $refetch);
                    }
                    $row[$this->getSeparatorColumn($n)] = '';
                }
            }
            // get status of last merge-up job
            $badge = $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $defaultBranch, 'Merge-up');
            $row['Workflow status'] = $badge;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Get a separator which is unique for array key purposes but provides the same rendered HTML result.
     * Increments $n in place each time it's called.
     */
    private function getSeparatorColumn(int &$n): string
    {
        $column = '|' . str_repeat(' ', $n);
        $n++;
        return $column;
    }

    private function getMergeUpDetail(
        string $account,
        string $repo,
        string $mergeFrom,
        string $mergeInto,
        AbstractRequester $requester,
        bool $refetch
    ): string {
        $path = "/repos/$account/$repo/compare/$mergeInto...$mergeFrom";
        $json = $requester->fetch($path, '', $account, $repo, $refetch);
        $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
        $compareUrl = $needsMergeUp
            ? "https://github.com/$account/$repo/compare/$mergeInto...$mergeFrom:needs-merge-up"
            : '';
        return $needsMergeUp ? $compareUrl : 'up-to-date';
    }
}
