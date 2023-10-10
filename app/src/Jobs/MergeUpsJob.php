<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class MergeUpsJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'MergeUpsJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('merge-ups', $refetch);
    }

    protected function getTimeMatrix(): array
    {
        $skip = [];
        $run = [430];
        return [
            'mon' => $run,
            'tue' => $skip,
            'wed' => $run,
            'thu' => $skip,
            'fri' => $run,
            'sat' => $skip,
            'sun' => $skip,
        ];
    }
}
