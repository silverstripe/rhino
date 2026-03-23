<?php

namespace App\Tests\Processors;

use App\DataFetcher\Requesters\RestRequester;
use App\Misc\CmsBuildsManager;
use App\Processors\CmsBuildsProcessor;

/**
 * CmsBuildsProcessor subclass with injected fakes for isolated unit testing.
 */
class TestCmsBuildsProcessor extends CmsBuildsProcessor
{
    /**
     * Initialises the processor with a fake manager and a hardcoded branch map.
     */
    public function __construct(
        private FakeCmsBuildsManager $manager,
        private array $branchesByRepo
    ) {
    }

    /**
     * Returns the injected fake manager instead of creating a real one.
     */
    protected function createCmsBuildsManager(): CmsBuildsManager
    {
        return $this->manager;
    }

    /**
     * Returns a FakeRestRequester instead of creating a real HTTP requester.
     */
    protected function createRequester(): RestRequester
    {
        return new FakeRestRequester();
    }

    /**
     * Returns a single hardcoded module entry for silverstripe-cms.
     */
    protected function getModuleVarsList(): array
    {
        return [
            ['silverstripe', 'silverstripe-cms', 'regular'],
        ];
    }

    /**
     * Returns the hardcoded branch data for the given repo from the injected branch map.
     */
    protected function getRepositoryBranches(
        RestRequester $requester,
        bool $refetch,
        string $account,
        string $repo
    ): array {
        return $this->branchesByRepo[$repo];
    }

    /**
     * Returns a predictable badge string for testing rather than fetching a real GHA status.
     */
    protected function getGhaStatusBadge(
        RestRequester $requester,
        bool $refetch,
        string $account,
        string $repo,
        string $branch,
        string $runName
    ): string {
        return "[status-badge {$branch}]";
    }
}
