<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class RecentMergedPrsJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'RecentMergedPrsJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('merged-prs', $refetch);
    }

    protected function getTimeMatrix(): array
    {
        $skip = [];
        $run = [330];
        return [
            'mon' => $run, // << see what happened last week
            'tue' => $skip,
            'wed' => $run, // << sprint ended tuesday
            'thu' => $skip,
            'fri' => $skip,
            'sat' => $skip,
            'sun' => $skip,
        ];
    }
}
