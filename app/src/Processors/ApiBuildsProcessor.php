<?php

namespace App\Processors;

use App\DataFetcher\Apis\TravisApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\RestRequester;
use App\DataFetcher\Misc\Logger;

// travis-ci.com does not allow unpaid plans to access random public repos
// and it only has a single API token, does not allow the creation of a seperate API token
// with public read-only persmission, so this is basically a no-go except for local dev environments

class ApiBuildsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'api-builds';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    /*
    created - waiting for free workers
    started - running
    passed - green
    errored - red
    failed - red
    canceled - grey - yes it's a typo in travis api
    unknown - not in API, added by this script
    */
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
                    if (['created', 'started'].indexOf(v) != -1) {
                        c = 'khaki';
                    } else if (['passed'].indexOf(v) != -1) {
                        c = 'palegreen';
                    } else if (['failed', 'errored'].indexOf(v) != -1) {
                        c = 'rgb(255, 107, 107)';
                    } else if (['canceled', 'unknown'].indexOf(v) != -1) {
                        c = 'silver';
                    }
                    if (c) {
                        td.style.background = c;
                    }
                }
                // sort by status
                var haveClickedNextMinorStatus = false;
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (!haveClickedNextMinorStatus && th.hasAttribute('_sorttype') && th.innerText == 'nextMinorStatus') {
                            th.click();
                            haveClickedNextMinorStatus = true;
                        }
                        if (haveClickedNextMinorStatus && th.hasAttribute('_sorttype') && th.innerText == 'nextPatchStatus') {
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
        $apiConfig = new TravisApiConfig();
        $requester = new RestRequester($apiConfig);
        $modules = Consts::MODULES;
    
        $pagination = '@pagination';
        $rows = [];
    
        // get repository id's
        $account = 'silverstripe';
        $repo = 'meta';
        $repoIds = [];
        for ($offset = 0; $offset < 1000; $offset += 100) {
            $path = "/repos?private=false&limit=100&offset={$offset}";
            $json = $requester->fetch($path, '', $account, $repo, $refetch);
            foreach ($json->root->repositories ?? [] as $obj) {
                if (!in_array($obj->owner_name, ['silverstripe'])) {
                    continue;
                }
                $repoIds[$obj->name] = $obj->id;
            }
            if (is_null($json->root->$pagination->next ?? null)) {
                break;
            }
        }
    
        $moduleType = 'regular'; // not interested in tooling
        foreach ($modules[$moduleType] as $account => $repos) {
            foreach ($repos as $repo) {
                $branchData = [
                    'next-minor' => ['branch' => -1, 'number' => '', 'state' => 'unknown', 'commit' => ''],
                    'next-patch' => ['branch' => -1, 'number' => '', 'state' => 'unknown', 'commit' => ''],
                    'prev-minor'=> ['branch' => -1, 'number' => '', 'state' => 'unknown', 'commit' => ''],
                ];
                // branches
                if (!isset($repoIds[$repo])) {
                    Logger::singleton()->log("Could not find repoId for {$repo}");
                    continue;
                } else {
                    $repoId = $repoIds[$repo];
                    $branchNames = [];
                    for ($offset = 0; $offset < 1000; $offset += 100) {
                        $path = "/repo/$repoId/branches?exists_on_github=true&limit=100&offset=$offset";
                        $json = $requester->fetch($path, '', $account, $repo, $refetch);
                        foreach ($json->root->branches ?? [] as $obj) {
                            $branchNames[] = $obj->name;
                        }
                        if (is_null($json->root->$pagination->next ?? null)) {
                            break;
                        }
                    }
                    sort($branchNames);
                    // major
                    foreach ($branchNames as $branch) {
                        if (preg_match('#^[1-9]$#', $branch) && $branch > $branchData['next-minor']['branch']) {
                            $branchData['next-minor']['branch'] = $branch;
                        }
                    }
                    // minors
                    foreach ($branchNames as $branch) {
                        if (preg_match('#^([1-9])\.([0-9]{1,2})$#', $branch, $m)) {
                            if ($m[1] == $branchData['next-minor']['branch']) {
                                if ($branch > $branchData['next-patch']['branch']) {
                                    $branchData['prev-minor']['branch'] = $branchData['next-patch']['branch'];
                                    $branchData['next-patch']['branch'] = $branch;
                                }
                            }
                        }
                    }
                    // get builds
                    foreach ($branchData as $type => $arr) {
                        $branch = $arr['branch'];
                        if ($branch == -1) {
                            continue;
                        }
                        $path = "/repo/$repoId/builds?branch.name=$branch&event_type=push,api,cron&sort_by=number:desc&limit=1";
                        $json = $requester->fetch($path, '', $account, $repo, $refetch);
                        if (empty($json->root->builds ?? [])) {
                            continue;
                        }
                        $branchData[$type]['number'] = $json->root->builds[0]->number;
                        $branchData[$type]['state'] = $json->root->builds[0]->state;
                        $branchData[$type]['commit'] = $json->root->builds[0]->commit->sha;
                    }
                }
                $rows[] = [
                    'account' => $account,
                    'repo' => $repo,
                    'repoId' => $repoId,
                    'link' => "https://travis-ci.com/github/$account/$repo/branches",
                    'nextMinorBranch' => $branchData['next-minor']['branch'] . '.x-dev',
                    'nextMinorStatus' => $branchData['next-minor']['state'],
                    'nextPatchBranch' => $branchData['next-patch']['branch'] . '.x-dev',
                    'nextPatchStatus' => $branchData['next-patch']['state'],
                    'prevMinorBranch' => $branchData['prev-minor']['branch'] . '.x-dev',
                    'prevMinorStatus' => $branchData['prev-minor']['state'],
                ];
            }
        }
        return $rows;
    }
}
