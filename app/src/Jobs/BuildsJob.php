<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class BuildsJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'BuildsJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('builds', $refetch);
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
