<?php

namespace App\Tests\Misc;

use App\Misc\BuildsPipeline;

/**
 * BuildsPipeline subclass that records method calls as events rather than running processors.
 */
class TestBuildsPipeline extends BuildsPipeline
{
    public array $events = [];

    /**
     * Records a 'prime' event instead of loading real CMS data.
     */
    protected function primeCmsBuildsData(bool $refetch): void
    {
        $this->events[] = ['prime', $refetch];
    }

    /**
     * Records a 'run' event instead of executing a real processor.
     */
    protected function runProcessor(string $type, bool $refetch): void
    {
        $this->events[] = ['run', $type, $refetch];
    }
}
