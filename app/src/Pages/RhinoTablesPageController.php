<?php

namespace App\Pages;

use App\Processors\AbstractProcessor;
use App\Utils\DateTimeUtil;
use PageController;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class RhinoTablesPageController extends PageController
{
    public function getHtmlTables(): ArrayList
    {
        $arr = [];
        $dir = ASSETS_PATH . '/html';
        foreach ($this->getProcessorClasses() as $class) {
            $processor = new $class();
            $type = $processor->getType();
            if ($type == 'standards') {
                continue;
            }
            $sortOrder = $processor->getSortOrder();
            $filename = "{$type}.html";
            if (file_exists("{$dir}/{$filename}")) {
                while(array_key_exists($sortOrder, $arr)) {
                    $sortOrder++;
                }
                $arr[$sortOrder] = $type;
            }
        }
        ksort($arr);
        $list = new ArrayList();
        foreach ($arr as $table) {
            $list->add(new ArrayData(['Table' => $table]));
        }
        return $list;
    }

    public function getLastRun(): string
    {
        return $this->getRun([QueuedJob::STATUS_COMPLETE], 'JobFinished');
    }

    public function getNextRun(): string
    {
        $jobStatuses = [QueuedJob::STATUS_NEW, QueuedJob::STATUS_INIT, QueuedJob::STATUS_RUN];
        return $this->getRun($jobStatuses, 'StartAfter');
    }

    public function getHtmlContent(): string
    {
        $table = $this->getRequest()->getVar('t') ?? '';
        $files = scandir(ASSETS_PATH . '/html');
        foreach ($files as $file) {
            if ($file === "$table.html") {
                return file_get_contents(ASSETS_PATH . "/html/$file");
            }
        }
        return '';
    }

    private function getProcessorClasses(): array
    {
        $classes = [];
        foreach (ClassInfo::subclassesFor(AbstractProcessor::class, false) as $class) {
            $classes[] = $class;
        }
        return $classes;
    }

    private function getRun(array $jobStatuses, string $field): string
    {
        $type = $this->getRequest()->getVar('t') ?: '';
        $processor = $this->createProcessor($type);
        if (!$processor) {
            return '';
        }
        $filter = [
            'Implementation' => str_replace('Processor', 'Job', get_class($processor)),
            'JobStatus' => $jobStatuses,
        ];
        $descriptor = QueuedJobDescriptor::get()->filter($filter)->sort('ID DESC')->limit(1)->first();
        if (!$descriptor) {
            return '';
        }
        return DateTimeUtil::formatMysqlTimestamp($descriptor->$field);
    }

    private function createProcessor(string $type): ?AbstractProcessor
    {
        if (!$type) {
            return null;
        }
        foreach ($this->getProcessorClasses() as $class) {
            $processor = new $class();
            if ($type !== $processor->getType()) {
                continue;
            }
            return $processor;
        }
        return null;
    }
}
