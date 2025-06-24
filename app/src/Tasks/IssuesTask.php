<?php

namespace App\Tasks;

class IssuesTask extends BaseTask
{
    protected string $title = 'IssuesTask';

    protected static string $description = 'Fetch GitHub issues';

    protected string $type = 'issues';
}
