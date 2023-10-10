<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\RestRequester;
use Exception;

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
                    var v = td.innerText;
                    var c = '';
                    if (['needs-merge-up'].indexOf(v) != -1) {
                        c = 'khaki';
                    } else if (['up-to-date'].indexOf(v) != -1) {
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
                // sort by muStat
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'muStat') {
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
        $modules = Consts::MODULES;

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo];
                }
            }
        }

        // $varsList = [
        //     ['silverstripe', 'silverstripe-elemental'],
        //     // ['silverstripe', 'silverstripe-framework'],
        //     //['silverstripe', 'silverstripe-frameworktest'],
        // ];

        $minorBrnRx = '#^([1-9])\.([0-9]+)$#';
        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;

            // get branches available
            $branches = [];
            $json = $requester->fetch("/repos/$account/$repo/branches", '', $account, $repo, $refetch);
            foreach ($json->root ?? [] as $branch) {
                if (!$branch) {
                    continue;
                }
                $name = $branch->name;
                if (preg_match($minorBrnRx, $name)) {
                    $branches[] = $name;
                }
            }
            $arr = [
                'account' => $account,
                'repo' => $repo,
                'muStat' => '',
            ];
            if ($repo == 'silverstripe-frameworktest') {
                $branches = ['1', '0.4'];
            } else {
                usort($branches, function ($a, $b) use ($minorBrnRx) {
                    preg_match($minorBrnRx, $a, $ma);
                    preg_match($minorBrnRx, $b, $mb);
                    $n = (int) $ma[1] <=> (int) $mb[1];
                    if ($n != 0) {
                        return $n;
                    }
                    return (int) $ma[2] <=> (int) $mb[2];
                });
                $branches = array_reverse($branches);
            }
            if (count($branches) == 0) {
                continue;
            }

            $nextPatBrn = $branches[0];
            $nextMinBrn = substr($nextPatBrn, 0, 1);

            $blankMu = 'nothing';
            // 'cm' = current major, 'xm' = cross major, 'pm' = prev major
            foreach (['cm', 'xm', 'pm'] as $prefix) {
                // cross major
                if ($prefix == 'xm') {
                    $arr["|"] = '';
                    $arr["{$prefix}Mu"] = $blankMu;
                    $arr["{$prefix}CmpUrl"] = '';
                    // merge into next-patch if it ends in *.0 - e.g. 5.0...4
                    // else merge into prev-minor e.g. - e.g. 5.1...4, if next-patch is 5.2
                    $mergeInto = $branches[0];
                    if (!preg_match('#\.0$#', $mergeInto)) {
                        $mergeInto -= 0.1;
                        $mergeInto = sprintf('%.1f', $mergeInto);
                    }
                    $lastMajor = $nextMinBrn - 1;
                    $bs = array_filter($branches, function ($branch) use ($lastMajor) {
                        return substr($branch, 0, 1) == $lastMajor;
                    });
                    $bs = array_values($bs);
                    if (count($bs) == 0) {
                        continue;
                    }
                    $path = "/repos/$account/$repo/compare/$mergeInto...$lastMajor";
                    $json = $requester->fetch($path, '', $account, $repo, $refetch);
                    $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                    $arr["{$prefix}Mu"] = $needsMergeUp ? 'needs-merge-up' : 'up-to-date';
                    $arr["{$prefix}CmpUrl"] = $needsMergeUp
                        ? "https://github.com/$account/$repo/compare/$mergeInto...$lastMajor"
                        : '';
                    continue;
                }
                // current major, previous major
                if ($prefix == 'cm') {
                    $arr["| "] = '';
                }
                if ($prefix == 'pm') {
                    $nextMinBrn = $nextMinBrn - 1;
                    $arr["|  "] = '';
                }
                $arr["{$prefix}NextMinBrn"] = '';
                $arr["{$prefix}Mu"] = $blankMu;
                $arr["{$prefix}CmpUrl"] = '';
                $arr["{$prefix}NextPatBrn"] = '';
                $arr["{$prefix}MuPrevMin"] = '';
                $arr["{$prefix}CmpUrlPrevMin"] = '';
                $arr["{$prefix}PrevMinBrn"] = '';
                $bs = array_filter($branches, function ($branch) use ($nextMinBrn) {
                    return substr($branch, 0, 1) == $nextMinBrn;
                });
                $bs = array_values($bs);
                if (count($bs) == 0) {
                    continue;
                }
                $nextPatBrn = $bs[0];
                $prevMinBrn = count($bs) > 1 ? $bs[1] : '';

                // 4...4.12
                $arr["{$prefix}NextMinBrn"] = "{$nextMinBrn}.x-dev";
                $path = "/repos/$account/$repo/compare/$nextMinBrn...$nextPatBrn";
                $json = $requester->fetch($path, '', $account, $repo, $refetch);
                $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                $arr["{$prefix}Mu"] = $needsMergeUp ? 'needs-merge-up' : 'up-to-date';
                $arr["{$prefix}CmpUrl"] = $needsMergeUp
                    ? "https://github.com/$account/$repo/compare/$nextMinBrn...$nextPatBrn"
                    : '';
                $arr["{$prefix}NextPatBrn"] = "{$nextPatBrn}.x-dev";

                // 4.12...4.11
                if ($prevMinBrn) {
                    $path = "/repos/$account/$repo/compare/$nextPatBrn...$prevMinBrn";
                    $json = $requester->fetch($path, '', $account, $repo, $refetch);
                    $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                    $arr["{$prefix}MuPrevMin"] = $needsMergeUp ? 'needs-merge-up' : 'up-to-date';
                    $arr["{$prefix}CmpUrlPrevMin"] = $needsMergeUp
                        ? "https://github.com/$account/$repo/compare/$nextPatBrn...$prevMinBrn"
                        : '';
                    $arr["{$prefix}PrevMinBrn"] = "{$prevMinBrn}.x-dev";
                }
            }
            // get default branch
            $json = $requester->fetch("/repos/$account/$repo?paginate=0", '', $account, $repo, $refetch);
            $defaultbranch = $json->root->default_branch;
            // get status of last merge-up job
            $badge = $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $defaultbranch, 'Merge-up');
            $arr['muStat'] = $badge;
            $rows[] = $arr;
        }
        return $rows;
    }
}
