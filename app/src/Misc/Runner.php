<?php

namespace App\Misc;

use InvalidArgumentException;
use App\Processors\AbstractProcessor;
use App\Services\OutputUtil;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injectable;

class Runner
{
    use Injectable;

    public function run(string $type, bool $refetch): void
    {
        $processor = $this->createProcessor($type);
        $rows = $processor->process($refetch);
        $this->outputCsv($type, $rows);
        $this->outputHtmlTable($type, $rows, $processor->getHtmlTableScript());
    }

    private function outputCsv(string $type, array $rows): void
    {
        $dir = $this->prepareDir('/public/assets/csv');
        $path = "{$dir}/{$type}.csv";
        OutputUtil::outputCsv($path, $rows);
    }

    private function outputHtmlTable(string $type, array $rows, string $script): void
    {
        $dir = $this->prepareDir('/public/assets/html');
        $path = "{$dir}/{$type}.html";
        OutputUtil::outputHtmlTable($path, $rows, $script);
        // convert status badges shortcode to html
        $s = file_get_contents($path);
        $s = $this->addInStatusBadges($s);
        file_put_contents($path, $s);
    }

    private function addInStatusBadges(string $s): string
    {
        return preg_replace_callback(
            '#\[status\-badge([^\]]+)\]#',
            function ($m) {
                $a = preg_split('# #', trim($m[1]));
                $data = [];
                foreach ($a as $kv) {
                    list($k, $v) = preg_split('#=#', $kv, 2);
                    $data[$k] = $v;
                }
                return $this->buildStatusBadge(
                    $data['metadata-sort'],
                    $data['metadata-status'],
                    $data['href'],
                    $data['src']
                );
            },
            $s
        );
    }

    private function buildStatusBadge(string $sort, string $status, string $href, string $src): string
    {
        // sort is used for column sorting
        // status is used for column filtering
        $cl = strpos($href, 'travis') !== false ? 'travis' : 'gha';
        $a = <<<EOT
            <div class="status-badge $cl">
                <div class="metadata">
                    <span class="metadata-sort">{$sort}</span>
                    <span class="metadata-status">{$status}</span>
                </div>
EOT;
        $b = !$href ? '' : <<<EOT
                <a href="{$href}" target="_blank">
                    <img src="{$src}">
                </a>
EOT;
        $c = '</div>';
        return $a . $b . $c;
    }

    private function prepareDir(string $dir): string
    {
        $absDir = BASE_PATH . $dir;
        if (!file_exists($absDir)) {
            mkdir($absDir, 0775);
            sleep(1);
        }
        return $absDir;
    }

    private function createProcessor(string $type): AbstractProcessor
    {
        $classes = ClassInfo::subclassesFor(AbstractProcessor::class, false);
        foreach ($classes as $class) {
            $processor = new $class();
            if ($processor->getType() == $type) {
                return $processor;
            }
        }
        throw new InvalidArgumentException("$type is not a valid type of processor");
    }
}
