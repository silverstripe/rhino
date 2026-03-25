<?php

namespace App\Misc;

/**
 * Orchestrates running the builds and cms-builds processors in sequence,
 * priming the CMS builds data between the two runs.
 */
class BuildsPipeline
{
    /**
     * Runs the full build pipeline: processes builds data, primes CMS data, then processes cms-builds.
     */
    public function run(bool $refetch): void
    {
        $this->runProcessor('builds', $refetch);
        $this->primeCmsBuildsData($refetch);
        $this->runProcessor('cms-builds', false);
    }

    /**
     * Loads CMS roadmap and module data into the manager ahead of the cms-builds processor run.
     */
    protected function primeCmsBuildsData(bool $refetch): void
    {
        $manager = new CmsBuildsManager();
        $manager->primeApiData($refetch);
    }

    /**
     * Delegates to the global Runner to execute a specific processor type.
     */
    protected function runProcessor(string $type, bool $refetch): void
    {
        Runner::singleton()->run($type, $refetch);
    }
}
