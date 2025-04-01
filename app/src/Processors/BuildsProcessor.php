<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\RestRequester;
use App\Misc\SupportedModulesManager;

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
        return <<<EOT
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
        $manager = new SupportedModulesManager();
        $modules = $manager->getModules();

        $apiConfig = new GitHubApiConfig();
        $requester = new RestRequester($apiConfig);

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo, $moduleType];
                }
            }
        }

        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo, $moduleType) = $vars;

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

            // Don't show unsupported branches for regular repos
            if ($moduleType == 'regular') {
                if ($nextMajBrn && !$manager->getMajorBranchIsSupported($repo, $nextMajBrn)) {
                    $nextMajBrn = '';
                }
                if ($nextMinBrn && !$manager->getMajorBranchIsSupported($repo, $nextMinBrn)) {
                    $nextMinBrn = '';
                    $nextPatBrn = '';
                }
                if ($pmMinBrn) {
                    $previousMajor = explode('.', $pmMinBrn)[0];
                    if (!$manager->getMajorBranchIsSupported($repo, $previousMajor)) {
                        $pmMinBrn = '';
                        $pmPatBrn = '';
                    }
                }
            }

            // Create the previous major, previous minor branch
            // e.g. 5.4.x-dev -> 5.3.x-dev
            // This will be empty if the previous major, current minor branch ends in .0 e.g. 5.0.x-dev
            $pmPrevMinBrn = '';
            if ($pmPatBrn && preg_match('/\.[1-9][0-9]*$/', $pmPatBrn)) {
                $pmPrevMinBrn = $pmPatBrn - 0.1;
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
                'nextMajGhaStat' => $nextMajBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextMajBrn, $runName)
                    : $this->buildBlankBadge(),

                // current major
                '| ' => '',
                'nextMinBrn' => $nextMinBrn ? ($nextMinBrn . '.x-dev') : '',
                'nextMinGhaStat' => $nextMinBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextMinBrn, $runName)
                    : $this->buildBlankBadge(),
                'nextPatBrn' => $nextPatBrn ? ($nextPatBrn . '.x-dev') : '',
                'nextPatGhaStat' => $nextPatBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $nextPatBrn, $runName)
                    : $this->buildBlankBadge(),

                // prev major
                '|' => '',

                'pmMinBrn' => $pmMinBrn ? ($pmMinBrn . '.x-dev') : '',
                'pmMinGhaStat' => $pmMinBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmMinBrn, $runName)
                    : $this->buildBlankBadge(),

                'pmPatBrn' => $pmPatBrn ? ($pmPatBrn . '.x-dev') : '',
                'pmPatGhaStat' => $pmPatBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmPatBrn, $runName)
                    : $this->buildBlankBadge(),

                'pmPrevMinBrn' => $pmPrevMinBrn ? ($pmPrevMinBrn . '.x-dev') : '',
                'pmPrevMinGhaStat' => $pmPrevMinBrn
                    ? $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $pmPrevMinBrn, $runName)
                    : $this->buildBlankBadge(),
            ];

            $rows[] = $row;
        }
        return $rows;
    }
}
