<?php

namespace App\Tasks;

use App\Misc\Runner;
use SilverStripe\Dev\BuildTask;

class IssuesTask extends BuildTask
{
    protected $title = 'IssuesTask';

    private static $segment = 'IssuesTask';

    public function run($request)
    {
        // Intended as a dev task, so by default will not refetch
        // Add refetch=1 to CLI call to get it to refetch
        $refetch = (bool) $request->getVar('refetch');
        Runner::singleton()->run('issues', $refetch);
    }
}
