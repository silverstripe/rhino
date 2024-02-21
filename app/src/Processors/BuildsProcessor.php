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

        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;

            if ($repo == 'silverstripe-frameworktest') {
                continue;
            }

            $minorBranches = [];
            $majorBranches = [];
            $minorBrnRx = '#^([1-9])\.([0-9]+)$#';
            $majorBrnRx = '#^([1-9])$#';
            $path = "/repos/$account/$repo/branches?paginate=0&per_page=100";
            $json = $requester->fetch($path, '', $account, $repo, $refetch);
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
            $nextPatBrn = '';
            $nextMinBrn = '';
            $pmMinBrn = '';
            $pmPatBrn = '';
            $currMajBrn = '';
            $nextMajBrn = '';
            if (count($minorBranches)) {
                $nextPatBrn = count($minorBranches) ? $minorBranches[0] : ''; // 4.7
                $nextMinBrn = substr($nextPatBrn, 0, 1); // 4
                $pmMinBrn = $nextMinBrn - 1; // 3
                $currMajBrn = preg_replace($minorBrnRx, '$1', $nextPatBrn);
                if (count($majorBranches) && $currMajBrn != $majorBranches[0]) {
                    $nextMajBrn = $majorBranches[0]; // 5
                }
            } else {
                if (count($majorBranches)) {
                    $currMajBrn = $majorBranches[0];
                } else {
                    continue;
                }
            }
            // see if there are any branches that match the previous minor branch
            if (!empty(array_filter($minorBranches, function ($branch) use ($pmMinBrn) {
                list($major,) = explode('.', $branch);
                return $major == $pmMinBrn;
            }))) {
                foreach ($minorBranches as $branch) {
                    if (strpos($branch, "$pmMinBrn.") === 0) {
                        if ($pmPatBrn == '') {
                            $pmPatBrn = $branch;
                        } else {
                            break;
                        }
                    }
                }
            };

            // remove any < 4 branches for linkfield
            // note: this is not duplicated code from the other silverstripe-linkfield conditional above
            if ($repo == 'silverstripe-linkfield') {
                if ($pmMinBrn == '3') {
                    $pmMinBrn = '';
                }
            }

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

                // next major
                'nextMajBrn' => $nextMajBrn ? ($nextMajBrn . '.x-dev') : '',
                'nextMajGhaStat' => $nextMajBrn == ''
                    ? $this->buildBlankBadge()
                    : $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextMajBrn, $runName),

                // current major
                '| ' => '',
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

            $rows[] = $row;
        }
        return $rows;
    }
}
