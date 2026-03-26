<?php

namespace App\Misc;

use SilverStripe\SupportedModules\MetaData;

class SupportedModulesManager
{
    private array $modules = [];

    private array $cmsMajorToBranches = [];

    // Repos miscategorised upstream as misc that are actually regular installable modules.
    private const REGULAR_OVERRIDES = ['silverstripe-non-blocking-sessions', 'doorman'];

    public function getModules(): array
    {
        if (!empty($this->modules)) {
            return $this->modules;
        }
        $repoData = MetaData::getAllRepositoryMetaData();
        $this->modules = [
            'regular' => [],
            'other' => [],
        ];
        // Adapt json keys to the keys used in rhino
        $types = [
            MetaData::CATEGORY_SUPPORTED => 'regular',
            MetaData::CATEGORY_WORKFLOW => 'other',
            MetaData::CATEGORY_TOOLING => 'other',
            MetaData::CATEGORY_MISC => 'other',
        ];
        foreach ($types as $category => $type) {
            foreach ($repoData[$category] as $module) {
                $majorVersionMapping = $this->removeUnsupportedMappings($module['majorVersionMapping']);
                if (empty($majorVersionMapping)) {
                    continue;
                }
                [$account, $repo] = explode('/', $module['github']);
                if (in_array($repo, self::REGULAR_OVERRIDES, true)) {
                    $type = 'regular';
                }
                $this->modules[$type][$account] ??= [];
                $this->modules[$type][$account][] = $repo;
                $this->cmsMajorToBranches[$repo] = $majorVersionMapping;
            }
        }
        return $this->modules;
    }

    public function getMajorBranchIsSupported(string $repo, string $majorBranch): bool
    {
        if (empty($this->cmsMajorToBranches)) {
            $this->getModules();
        }
        $cmsMajorToBranches = $this->cmsMajorToBranches[$repo] ?? [];
        foreach ($cmsMajorToBranches as $cmsMajor => $branches) {
            if ($cmsMajor === '*') {
                return true;
            }
            if (in_array($majorBranch, $branches, true)) {
                return true;
                break;
            }
        }
        return false;
    }

    public function canHaveReleases(string $repo): bool
    {
        if (empty($this->cmsMajorToBranches)) {
            $this->getModules();
        }
        $cmsMajorToBranches = $this->cmsMajorToBranches[$repo] ?? [];
        foreach ($cmsMajorToBranches as $cmsMajor => $branches) {
            if ($cmsMajor !== '*') {
                return true;
            }
        }
        return false;
    }

    private function removeUnsupportedMappings(array $majorVersionMapping): array
    {
        $newMapping = [];
        foreach ($majorVersionMapping as $major => $map) {
            if ($major !== '*' && $major < MetaData::LOWEST_SUPPORTED_CMS_MAJOR) {
                continue;
            }
            $newMapping[$major] = $map;
        }
        return $newMapping;
    }
}
