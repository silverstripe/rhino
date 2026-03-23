<?php

namespace App\Processors;

use App\DataFetcher\Requesters\RestRequester;
use App\Jobs\BuildsJob;
use App\Misc\CmsBuildsManager;

/**
 * Builds a CI status table grouped by CMS version, showing build badges for each
 * visible CMS major and minor version across all supported module repositories.
 */
class CmsBuildsProcessor extends BuildsProcessor
{
    private string $defaultSortHeader = '';

    /**
     * Returns 'cms-builds' as the processor type identifier.
     */
    public function getType(): string
    {
        return 'cms-builds';
    }

    /**
     * Returns the sort order for this processor in the UI.
     */
    public function getSortOrder(): int
    {
        return 3;
    }

    /**
     * Returns the BuildsJob class as the queued job implementation for this processor.
     */
    public function getJobImplementation(): string
    {
        return BuildsJob::class;
    }

    /**
     * Returns a JS snippet that auto-clicks the default sort column header on page load, or empty string if none.
     */
    public function getHtmlTableScript(): string
    {
        if ($this->defaultSortHeader === '') {
            return '';
        }

        $defaultSortHeader = $this->defaultSortHeader;
        return <<<EOT
            (function() {
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == '{$defaultSortHeader}') {
                            window.clearInterval(interval);
                            th.click();
                            return;
                        }
                    }
                }, 250);
            })();
        EOT;
    }

    /**
     * Iterates over visible repos and CMS versions to build a row of CI badge cells per module.
     */
    public function process(bool $refetch): array
    {
        $requester = $this->createRequester();
        $manager = $this->createCmsBuildsManager();
        $manager->load($requester, $refetch);

        $visibleRepos = array_flip($manager->getVisibleRepoNames());
        $cmsMajors = $manager->getVisibleCmsMajors();
        $this->defaultSortHeader = $this->deriveDefaultSortHeader($manager, $cmsMajors);

        $rows = [];
        foreach ($this->getModuleVarsList() as $vars) {
            [$account, $repo] = $vars;
            if (!isset($visibleRepos[$repo])) {
                continue;
            }

            $branchData = $this->getRepositoryBranches($requester, $refetch, $account, $repo);
            $minorBranches = $branchData['minorBranches'];
            $majorBranches = $branchData['majorBranches'];
            if ($minorBranches === [] && $majorBranches === []) {
                continue;
            }

            $row = [
                'account' => $account,
                'repo' => $repo,
            ];
            $hasBranchData = false;
            foreach ($cmsMajors as $cmsMajor) {
                $majorCell = $this->buildCmsCell(
                    $requester,
                    $refetch,
                    $account,
                    $repo,
                    $this->getRunName($repo),
                    $manager->getMappedMajorBranches($repo, $cmsMajor),
                    $majorBranches
                );
                $row[$cmsMajor] = $majorCell;
                $hasBranchData = $hasBranchData || $majorCell !== '';

                foreach ($manager->getVisibleCmsVersionsForMajor($cmsMajor) as $cmsVersion) {
                    $cell = $this->buildCmsCell(
                        $requester,
                        $refetch,
                        $account,
                        $repo,
                        $this->getRunName($repo),
                        $manager->getMappedMinorBranches($repo, $cmsVersion),
                        $minorBranches
                    );
                    $row[$cmsVersion] = $cell;
                    $hasBranchData = $hasBranchData || $cell !== '';
                }
            }

            if ($hasBranchData) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Builds a cell value containing CI badges for each mapped branch of a repo/version combination.
     */
    private function buildCmsCell(
        RestRequester $requester,
        bool $refetch,
        string $account,
        string $repo,
        string $runName,
        array $mappedBranches,
        array $existingBranches
    ): string {
        $parts = [];
        foreach ($mappedBranches as $branch) {
            $effectiveBranch = in_array($branch, $existingBranches, true)
                ? $branch
                : $this->findLatestExistingBranchForMajor($branch, $existingBranches);
            if ($effectiveBranch === null) {
                continue;
            }
            $badge = $this->getGhaStatusBadge($requester, $refetch, $account, $repo, $effectiveBranch, $runName);
            $parts[] = "{$badge} {$effectiveBranch}.x-dev";
        }
        return implode(' / ', $parts);
    }

    /**
     * Returns the latest existing branch that shares the same major version prefix, or null if none exists.
     */
    private function findLatestExistingBranchForMajor(string $branch, array $existingBranches): ?string
    {
        $major = explode('.', $branch)[0];
        foreach ($existingBranches as $existing) {
            if (str_starts_with($existing, $major . '.')) {
                return $existing;
            }
        }
        return null;
    }

    /**
     * Returns the column header to sort by default — the latest visible minor version, or major if none.
     */
    private function deriveDefaultSortHeader(CmsBuildsManager $manager, array $cmsMajors): string
    {
        foreach ($cmsMajors as $cmsMajor) {
            $versions = $manager->getVisibleCmsVersionsForMajor($cmsMajor);
            if ($versions !== []) {
                return $versions[0];
            }
            return $cmsMajor;
        }
        return '';
    }

    /**
     * Creates the CmsBuildsManager instance used to load version and mapping data.
     */
    protected function createCmsBuildsManager(): CmsBuildsManager
    {
        return new CmsBuildsManager();
    }
}
