<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\RestRequester;

class BuildsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'builds';
    }

    public function getSortOrder(): int
    {
        return 2;
    }

    public function getHtmlTableScript(): string
    {
        return $this->getTravisBadgeScript() . <<<EOT
            (function() {

                // sort by nextMinGhaStat desc
                var interval = window.setInterval(function() {
                    // if (!window.ghaBadgesLoaded) {
                    //    return;
                    //}
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'nextMinGhaStat') {
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
        $modules = Consts::MODULES;

        $apiConfig = new GitHubApiConfig();
        $requester = new RestRequester($apiConfig);

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo];
                }
            }
        }

        // $varsList = [
        //     // ['silverstripe', 'silverstripe-linkfield'],
        //     // ['silverstripe', 'silverstripe-sqlite3'],
        //     // ['silverstripe', 'gha-run-tests'],
        //     // ['silverstripe', 'gha-action-ci'],
        // ];

        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;

            if ($repo == 'silverstripe-frameworktest') {
                continue;
            }

            // get minor branches available
            $branches = [];
            // $branchesIncludingReleases = [];
            $minorBrnRx = '#^([1-9])\.([0-9]+)$#';
            // $minorBrnWithReleasesRx = '#^([1-9])\.([0-9]+)(\-release|)$#';
            $path = "/repos/$account/$repo/branches?paginate=0&per_page=100";
            $json = $requester->fetch($path, '', $account, $repo, $refetch);
            foreach ($json->root ?? [] as $branch) {
                if (!$branch) {
                    continue;
                }
                $name = $branch->name;
                // if (!preg_match($minorBrnWithReleasesRx, $name)) {
                //     continue;
                // }
                // $branchesIncludingReleases[] = $name;
                if (preg_match($minorBrnRx, $name)) {
                    $branches[] = $name;
                }
            }
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
            // quick hack for linkfield which only has a `4` branch while it's in dev
            // delete this onece linkfield is supported and stable
            if (count($branches) == 0 && $repo == 'silverstripe-linkfield') {
                $branches = ['4.0'];
            }
            if (count($branches) == 0) {
                continue;
            }
            // $nextPatRelBrn = $branches[0] . '-release'; // 4.7-release
            // $nextPatRelBrn = in_array($nextPatRelBrn, $branchesIncludingReleases) ? $nextPatRelBrn : '';
            $nextPatBrn = $branches[0]; // 4.7
            $nextMinBrn = $nextPatBrn[0]; // 4
            # $nextMajBrn = $nextMinBrn + 1;
            $pmMinBrn = $nextMinBrn - 1; // 3
            $pmPatBrn = '';
            $pmPrevMinBrn = '';
            // see if there are any branches that match the previous minor branch
            if (!empty(array_filter($branches, function ($branch) use ($pmMinBrn) {
                list($major,) = explode('.', $branch);
                return $major == $pmMinBrn;
            }))) {
                foreach ($branches as $branch) {
                    if (strpos($branch, "$pmMinBrn.") === 0) {
                        if ($pmPatBrn == '') {
                            $pmPatBrn = $branch;
                        } else {
                            $pmPrevMinBrn = $branch;
                            break;
                        }
                    }
                }
            };

            $skipCurrentMajor = false;
            if (in_array($repo, [
                'silverstripe-postgresql',
                'silverstripe-sqlite3'
            ])) {
                $skipCurrentMajor = true;
            }

            $runName = 'CI';
            if (strpos($repo, 'gha-') === 0) {
                $runName = 'Action CI';
            }

            $row = [
                'account' => $account,
                'repo' => $repo,
                // 'link' => "https://travis-ci.com/github/$account/$repo/branches",

                // next major

                // 'nextMajBrn' => $nextMajBrn . '.x-dev',
                // 'nextMajGhaStat' => $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextMajBrn),

                // current major

                'nextMinBrn' => $skipCurrentMajor ? '' : $nextMinBrn . '.x-dev',
                'nextMinGhaStat' => $skipCurrentMajor
                    ? $this->buildBlankBadge()
                    : $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextMinBrn, $runName),
                'nextPatBrn' => $skipCurrentMajor ? '' : $nextPatBrn . '.x-dev',
                'nextPatGhaStat' => $skipCurrentMajor
                    ? $this->buildBlankBadge()
                    : $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextPatBrn, $runName),

                // prev major
                '|' => '',

                'pmMinBrn' => $pmMinBrn ? ($pmMinBrn . '.x-dev') : '',
                'pmMinGhaStat' => $pmMinBrn
                    ? ($this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmMinBrn, $runName))
                    : $this->buildBlankBadge(),

                'pmPatBrn' => $pmPatBrn ? ($pmPatBrn . '.x-dev') : '',
                'pmPatGhaStat' => $pmPatBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmPatBrn, $runName)
                    : $this->buildBlankBadge(),

                'pmPrevMinBrn' => '',
                'pmPrevMinGhaStat' => $this->buildBlankBadge(),
            ];

            // if ($pmprevMinBrn) {
            //     $row['pmprevMinBrn'] = $pmprevMinBrn . '.x-dev';
            //     $badge = $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmprevMinBrn, 'CI');
            //     $row['pmprevMinGhaStat'] = $badge;
            // }
            $rows[] = $row;
        }
        return $rows;
    }
}
