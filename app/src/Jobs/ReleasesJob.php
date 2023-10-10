<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class ReleasesJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'ReleasesJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('releases', $refetch);
    }

    protected function getTimeMatrix(): array
    {
        $skip = [];
        $run = [2300];
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
