<?php

namespace App\Tasks;

class OpenPrsTask extends BaseTask
{
    protected string $title = 'OpenPrsTask';

    protected static string $description = 'Fetch open PRs';

    protected string $type = 'open-prs';
}
