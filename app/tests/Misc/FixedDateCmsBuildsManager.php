<?php

namespace App\Tests\Misc;

use App\Misc\CmsBuildsManager;
use DateTimeImmutable;

/**
 * CmsBuildsManager with a fixed current date for deterministic test results.
 */
class FixedDateCmsBuildsManager extends CmsBuildsManager
{
    /**
     * Initialises the manager with the fixed date to use in place of the real current date.
     */
    public function __construct(
        private DateTimeImmutable $currentDate,
        private array $locksteppedRepos = []
    ) {
    }

    /**
     * Returns injected lockstepped metadata instead of calling the supported-modules API.
     *
     * @return array<string, array<string, string[]>>
     */
    protected function loadLocksteppedRepos(bool $refetch): array
    {
        return $this->locksteppedRepos;
    }

    /**
     * Returns the fixed date provided at construction rather than the real current date.
     */
    protected function getCurrentDateNZT(): DateTimeImmutable
    {
        return $this->currentDate;
    }
}
