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

        $minorBrnRx = '#^([1-9])\.([0-9]+)$#';
        $majorBrnRx = '#^([1-9])$#';
        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;

            // get branches available
            $minorBranches = [];
            $majorBranches = [];
            $json = $requester->fetch("/repos/$account/$repo/branches", '', $account, $repo, $refetch);
            foreach ($json->root ?? [] as $branch) {
                if (!$branch) {
                    continue;
                }
                $name = $branch->name;
                if (preg_match($minorBrnRx, $name)) {
                    $minorBranches[] = $name;
                }
                if (preg_match($majorBrnRx, $name)) {
                    $majorBranches[] = $name;
                }
            }
            $arr = [
                'account' => $account,
                'repo' => $repo,
                'muStat' => '',
            ];
            if ($repo == 'silverstripe-frameworktest') {
                $minorBranches = ['1', '0.4'];
            } else {
                usort($minorBranches, function ($a, $b) use ($minorBrnRx) {
                    preg_match($minorBrnRx, $a, $ma);
                    preg_match($minorBrnRx, $b, $mb);
                    $n = (int) $ma[1] <=> (int) $mb[1];
                    if ($n != 0) {
                        return $n;
                    }
                    return (int) $ma[2] <=> (int) $mb[2];
                });
                usort($majorBranches, function ($a, $b) use ($majorBrnRx) {
                    preg_match($majorBrnRx, $a, $ma);
                    preg_match($majorBrnRx, $b, $mb);
                    return (int) $ma[1] <=> (int) $mb[1];
                });
                $minorBranches = array_reverse($minorBranches);
                $majorBranches = array_reverse($majorBranches);
            }
            // remove any < 4 minor and major branches for linkfield
            if ($repo == 'silverstripe-linkfield') {
                $minorBranches = array_filter($minorBranches, function ($branch) {
                    return (int) $branch >= 4;
                });
                $majorBranches = array_filter($majorBranches, function ($branch) {
                    return (int) $branch >= 4;
                });
                // hack for linkfield which only has a `4` branch while it's in dev
                // this is done so that `$minorBranches[0]` below passes
                if (count($minorBranches) == 0) {
                    $minorBranches = ['4.0'];
                }
            }
            if (count($minorBranches) == 0) {
                continue;
            }

            $nextPatBrn = $minorBranches[0];
            $nextMinBrn = substr($nextPatBrn, 0, 1);
            $nextMajBrn = $majorBranches[0] != $nextMinBrn ? $majorBranches[0] : '';

            $blankMu = 'nothing';
            // 'nm' = next major,
            // 'xnm' = cross next major
            // 'cm' = current major
            // 'xm' = cross major
            // 'pm' = prev major
            foreach (['nm', 'xnm', 'cm', 'xm', 'pm'] as $prefix) {
                // cross major
                if ($prefix == 'xnm') {
                    $arr["|"] = '';
                    $arr["{$prefix}Mu"] = $blankMu;
                    // $arr["{$prefix}CmpUrl"] = '';
                    if ($nextMajBrn == '') {
                        continue;
                    }
                    $mergeInto = $nextMajBrn;
                    $path = "/repos/$account/$repo/compare/$nextMajBrn...$nextMinBrn";
                    $json = $requester->fetch($path, '', $account, $repo, $refetch);
                    $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                    $cmp = $needsMergeUp
                        ? "https://github.com/$account/$repo/compare/$nextMajBrn...$nextMinBrn:needs-merge-up"
                        : '';
                    $arr["{$prefix}Mu"] = $needsMergeUp ? $cmp : 'up-to-date';
                    continue;
                }
                if ($prefix == 'xm') {
                    $arr["| "] = '';
                    $arr["{$prefix}Mu"] = $blankMu;
                    // merge into next-patch if it ends in *.0 - e.g. 5.0...4
                    // else merge into prev-minor e.g. - e.g. 5.1...4, if next-patch is 5.2
                    $mergeInto = $minorBranches[0];
                    if (!preg_match('#\.0$#', $mergeInto)) {
                        $mergeInto -= 0.1;
                        $mergeInto = sprintf('%.1f', $mergeInto);
                    }
                    $lastMajor = $nextMinBrn - 1;
                    $bs = array_filter($minorBranches, function ($branch) use ($lastMajor) {
                        return substr($branch, 0, 1) == $lastMajor;
                    });
                    $bs = array_values($bs);
                    if (count($bs) == 0) {
                        continue;
                    }
                    $path = "/repos/$account/$repo/compare/$mergeInto...$lastMajor";
                    $json = $requester->fetch($path, '', $account, $repo, $refetch);
                    $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                    $cmp = $needsMergeUp
                        ? "https://github.com/$account/$repo/compare/$mergeInto...$lastMajor:needs-merge-up"
                        : '';
                    $arr["{$prefix}Mu"] = $needsMergeUp ? $cmp : 'up-to-date';
                    continue;
                }
                // next major, current major, previous major
                if ($prefix == 'nm') {
                    $arr["nmBrn"] = $nextMajBrn ? "{$nextMajBrn}.x-dev" : '';
                }
                if ($prefix == 'cm' || $prefix == 'pm') {
                    if ($prefix == 'cm') {
                        $arr["|   "] = '';
                    }
                    if ($prefix == 'pm') {
                        $nextMinBrn = $nextMinBrn - 1;
                        $arr["|    "] = '';
                    }
                    $arr["{$prefix}NextMinBrn"] = '';
                    $arr["{$prefix}Mu"] = $blankMu;
                    $arr["{$prefix}NextPatBrn"] = '';
                    $arr["{$prefix}MuPrevMin"] = '';
                    $arr["{$prefix}PrevMinBrn"] = '';
                    $bs = array_filter($minorBranches, function ($branch) use ($nextMinBrn) {
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
                    $cmp = $needsMergeUp
                        ? "https://github.com/$account/$repo/compare/$nextMinBrn...$nextPatBrn:needs-merge-up"
                        : '';
                    $arr["{$prefix}Mu"] = $needsMergeUp ? $cmp : 'up-to-date';
                    $arr["{$prefix}NextPatBrn"] = "{$nextPatBrn}.x-dev";

                    // 4.12...4.11
                    if ($prevMinBrn) {
                        $path = "/repos/$account/$repo/compare/$nextPatBrn...$prevMinBrn";
                        $json = $requester->fetch($path, '', $account, $repo, $refetch);
                        $needsMergeUp = ($json->root->ahead_by ?? 0) > 0;
                        $cmp = $needsMergeUp
                            ? "https://github.com/$account/$repo/compare/$nextPatBrn...$prevMinBrn:needs-merge-up"
                            : '';
                        $arr["{$prefix}MuPrevMin"] = $needsMergeUp ? $cmp : 'up-to-date';
                        $arr["{$prefix}PrevMinBrn"] = "{$prevMinBrn}.x-dev";
                    }
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
