<?php

namespace App\Tests\Processors;

use App\DataFetcher\Requesters\AbstractRequester;
use App\Misc\CmsBuildsManager;

/**
 * CmsBuildsManager stub with hardcoded data for use in processor tests.
 */
class FakeCmsBuildsManager extends CmsBuildsManager
{
    /**
     * Initialises the stub with hardcoded visible repos, CMS majors, versions, and branch mappings.
     */
    public function __construct(
        private array $visibleRepos,
        private array $cmsMajors,
        private array $versionsByMajor,
        private array $mappingsByRepo
    ) {
    }

    /**
     * No-op — data is provided at construction rather than loaded from an API.
     */
    public function load(AbstractRequester $requester, bool $refetch): void
    {
    }

    /**
     * Returns the hardcoded list of visible repo names.
     */
    public function getVisibleRepoNames(): array
    {
        return $this->visibleRepos;
    }

    /**
     * Returns the hardcoded list of visible CMS majors.
     */
    public function getVisibleCmsMajors(): array
    {
        return $this->cmsMajors;
    }

    /**
     * Returns the hardcoded visible minor versions for the given CMS major.
     */
    public function getVisibleCmsVersionsForMajor(string $cmsMajor): array
    {
        return $this->versionsByMajor[$cmsMajor] ?? [];
    }

    /**
     * Returns the hardcoded mapped minor branches for the given repo and CMS version.
     */
    public function getMappedMinorBranches(string $repo, string $cmsVersion): array
    {
        return $this->mappingsByRepo[$repo]['minor'][$cmsVersion] ?? [];
    }

    /**
     * Returns the hardcoded mapped major branches for the given repo and CMS major.
     */
    public function getMappedMajorBranches(string $repo, string $cmsMajor): array
    {
        return $this->mappingsByRepo[$repo]['major'][$cmsMajor] ?? [];
    }
}
