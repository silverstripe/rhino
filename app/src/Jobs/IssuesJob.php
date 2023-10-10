<?php

namespace App\Jobs;

use App\Misc\Runner;
use App\DataFetcher\Jobs\AbstractLoggableJob;

class IssuesJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'IssuesJob';
    }

    public function processWithLogging(): void
    {
        $refetch = true;
        Runner::singleton()->run('issues', $refetch);
    }

    protected function getTimeMatrix(): array
    {
        $run = [800, 1100, 1400];
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
