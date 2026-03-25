<?php

namespace App\Jobs;

use App\DataFetcher\Jobs\AbstractLoggableJob;
use App\Misc\BuildsPipeline;

class BuildsJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'BuildsJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        $pipeline = new BuildsPipeline();
        $pipeline->run($refetch);
    }

    protected function getTimeMatrix(): array
    {
        // job only pull in new modules
        $run = [130, 1145];
        return [
            'mon' => $run,
            'tue' => $run,
            'wed' => $run,
            'thu' => $run,
            'fri' => $run,
            'sat' => $run,
            'sun' => $run,
        ];
    }
}
