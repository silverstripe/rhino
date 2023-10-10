<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class StandardsJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'StandardsJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('standards', $refetch);
    }

    protected function getTimeMatrix(): array
    {
        $skip = [];
        $run = [830];
        return [
            'mon' => $run,
            'tue' => $skip,
            'wed' => $skip,
            'thu' => $run,
            'fri' => $skip,
            'sat' => $skip,
            'sun' => $skip,
        ];
    }
}
