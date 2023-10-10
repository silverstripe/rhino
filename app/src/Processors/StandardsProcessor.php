<?php

namespace App\Processors;

use App\Misc\MetaData;
use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\RestRequester;

class StandardsProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'standards';
    }

    public function getSortOrder(): int
    {
        return 7;
    }

    public function getHtmlTableScript(): string
    {
        return '';
    }

    public function process(bool $refetch): array
    {
        $apiConfig = new GitHubApiConfig();
        $requester = new RestRequester($apiConfig);
        $modules = Consts::MODULES;
        $modulesWithCustomTravis = MetaData::MODULES_WITH_CUSTOM_TRAVIS;
        $modulesWithoutNextMinorBranch = MetaData::MODULES_WITHOUT_NEXT_MINOR_BRANCH;

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    foreach (['next-minor', 'next-patch'] as $branchType) {
                        if (
                            $branchType == 'next-minor' &&
                            isset($modulesWithoutNextMinorBranch[$account]) &&
                            in_array($repo, $modulesWithoutNextMinorBranch[$account])
                        ) {
                            continue;
                        }
                        $varsList[] = [$account, $repo, $branchType];
                    }
                }
            }
        }

        $varsList = [
            ['silverstripe', 'silverstripe-admin', 'next-minor'],
        ];

        $rows = [];
        foreach ($varsList as $vars) {
            list($account, $repo, $branchType) = $vars;
            // get branches available
            // find the "highest" branch which should be the latest minor
            $ref = 0;
            $nextMinorRx = '#^([1-9])$#';
            $nextPatchRx = '#^([1-9])\.([0-9]+)$#';
            $json = $requester->fetch("/repos/$account/$repo/branches", '', $account, $repo, $refetch);
            foreach ($json->root ?? [] as $branch) {
                if (!$branch) {
                    continue;
                }
                $name = $branch->name;
                $rx = $branchType == 'next-minor' ? $nextMinorRx : $nextPatchRx;
                if (!preg_match($rx, $name)) {
                    continue;
                }
                if ($branchType == 'next-minor') {
                    if ((int) $name > (int) $ref) {
                        $ref = $name;
                    }
                } else {
                    preg_match($nextPatchRx, $name, $mname);
                    if (!preg_match($nextPatchRx, $ref, $mref)) {
                        $mref = [0, 0, 0];
                    }
                    if ((int) $mname[1] < (int) $mref[1]) {
                        continue;
                    }
                    if ((int) $mname[1] > (int) $mref[1] || (int) $mname[2] > (int) $mref[2]) {
                        $ref = $name;
                    }
                }
            }
            if (!$ref) {
                $ref = $branchType == 'next-minor' ? 'master' : 'missing';
            }
            if ($ref == 'missing') {
                $latestBranch = 'missing';
            } else {
                // important to suffix .x-dev otherwise excel will remove '.0' from next-patch branches
                $latestBranch = $ref == 'master' ? 'dev-master' : $ref . '.x-dev';
            }
            $repoKeyValues = [
                'account' => $account,
                'repo' => $repo,
                'branchType' => $branchType,
                'latestBranch' => $latestBranch
            ];
            $row = array_merge($repoKeyValues);
            if ($ref != 'missing') {
                $keyToFilename = [
                    'ghaci' => '.github/workflows/ci.yml',
                    'ghacikeepalive' => '.github/workflows/keepalive.yml',
                    'travis' => '.travis.yml',
                    'scrutinizer' => '.scrutinizer.yml',
                    'dotcodecov' => '.codecov.yml',
                    'codecov' => 'codecov.yml'
                ];
                // https://api.github.com/repos/silverstripe/silverstripe-asset-admin/contents/.travis.yml?ref=1.7
                foreach ($keyToFilename as $key => $filename) {
                    $row[$key] = 'no';
                    $path = "/repos/$account/$repo/contents/$filename?ref=$ref";
                    $json = $requester->fetch($path, '', $account, $repo, $refetch);
                    if (isset($json->root->content) && is_string($json->root->content)) {
                        $row[$key] = 'yes';
                    }
                }
                // $content = base64_decode($json->root->content);
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
