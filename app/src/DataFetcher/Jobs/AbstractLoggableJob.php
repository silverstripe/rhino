<?php

namespace App\DataFetcher\Jobs;

use Exception;
use App\DataFetcher\Misc\Logger;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

abstract class AbstractLoggableJob extends AbstractQueuedJob
{
    use Injectable;

    public function process()
    {
        $this->queueNextJob();
        $isComplete = true;
        try {
            $this->processWithLogging();
        } catch (Exception $e) {
            Logger::singleton()->log(get_class($e));
            Logger::singleton()->log($e->getMessage());
            Logger::singleton()->log($e->getTraceAsString());
            $isComplete = false;
        }
        foreach (Logger::singleton()->getLogs() as $log) {
            $this->addMessage($log);
        }
        $this->isComplete = $isComplete;
    }

    public function requireDefaultJob()
    {
        $filter = [
            'Implementation' => get_class($this),
            'JobStatus' => [
                QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_INIT,
                QueuedJob::STATUS_RUN
            ]
        ];
        if (QueuedJobDescriptor::get()->filter($filter)->count() > 0) {
            return;
        }
        $this->queueNextJob();
    }

    abstract protected function processWithLogging(): void;

    abstract protected function getTimeMatrix(): array;

    // ensure matrix has all days, and that it's in order
    private function cleanMatrix(array $matrix) {
        $m = [
            'mon' => [],
            'tue' => [],
            'wed' => [],
            'thu' => [],
            'fri' => [],
            'sat' => [],
            'sun' => [],
        ];
        foreach ($matrix as $day => $times) {
            $m[$day] = $times;
        }
        return $m;
    }

    private function deriveNextTime(array $matrix): string
    {
        $matrix = $this->cleanMatrix($matrix);

        $today = strtolower(date('D')); // tue
        $timeNow = (int) date('Gi'); // 730

        // get all times after time now
        $times = array_filter($matrix[$today], function($matrixTime) use ($timeNow) {
            return $timeNow < $matrixTime;
        });
        if (!empty($times)) {
            $mysqlDate = strtolower(date('Y-m-d'));
            // reset index
            $times = array_values($times);
            return $this->buildMysqlDateTime($mysqlDate, $times[0]);
        }
        // find the next day with matrix entries
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $daystwice = array_merge($days, $days);
        $foundToday = false;
        $d = 1;
        foreach ($daystwice as $day) {
            if (!$foundToday) {
                if ($day != $today) {
                    continue;
                }
                $foundToday = true;
                continue;
            }
            $mysqlDate = date('Y-m-d', strtotime("+{$d} day"));
            $times = $matrix[$day];
            $d++;
            if (empty($times)) {
                continue;
            }
            return $this->buildMysqlDateTime($mysqlDate, $times[0]);
        }
    }

    private function buildMysqlDateTime($mysqlDate, $time)
    {
        preg_match('#^([0-9]{1,2})([0-9]{2})$#', $time, $m);
        $mysqlTime = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
        return $mysqlDate . ' ' . $mysqlTime;
    }

    private function queueNextJob(): void
    {
        $matrix = $this->getTimeMatrix();
        $nextTime = $this->deriveNextTime($matrix);
        QueuedJobService::singleton()->queueJob(
            Injector::inst()->create(static::class),
            $nextTime
        );
    }
}
