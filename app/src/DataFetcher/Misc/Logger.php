<?php

namespace App\DataFetcher\Misc;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\TaskRunner;

class Logger
{
    use Injectable;

    private $logs = [];

    public function getLogs()
    {
        return $this->logs;
    }

    public function log(string $str)
    {
        $this->logs[] = $str;
        if (PHP_SAPI === 'cli') {
            // vendor/bin/sake dev/tasks
            echo rtrim($str, "\n") . "\n";
        } elseif (Controller::has_curr()) {
            $class = get_class(Controller::curr());
            if ($class == TaskRunner::class) {
                // http://website.test/dev/tasks/MyTask
                echo "$str<br>\n";
            }
        }
    }
}
