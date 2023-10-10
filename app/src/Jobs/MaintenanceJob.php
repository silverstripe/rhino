<?php

namespace App\Jobs;

use DateTime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use App\DataFetcher\Jobs\AbstractLoggableJob;
use App\DataFetcher\Models\ApiData;
use SilverStripe\ORM\DB;

class MaintenanceJob extends AbstractLoggableJob
{
    public function getTitle()
    {
        return 'Maintenance job';
    }

    public function processWithLogging(): void
    {
        // remove broken jobs
        QueuedJobDescriptor::get()->filter(['JobStatus' => 'Broken'])->removeAll();

        // remove old ApiData
        $date = new DateTime();
        $ts = strtotime('-1 week');
        $date->setTimestamp($ts);
        $ids = ApiData::get()->filter([
            'Created:LessThan' => $date->format('Y-m-d')
        ])->column('ID');
        if (!empty($ids)) {
            // use a raw query for performance
            $in = implode(',', $ids);
            DB::query("DELETE FROM ApiData WHERE ID IN ({$in});");
        }
    }

    protected function getTimeMatrix(): array
    {
        $skip = [];
        $run = [100];
        return [
            'mon' => $skip,
            'tue' => $skip,
            'wed' => $skip,
            'thu' => $skip,
            'fri' => $skip,
            'sat' => $skip,
            'sun' => $run,
        ];
    }
}
