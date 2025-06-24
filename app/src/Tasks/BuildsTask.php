<?php

namespace App\Tasks;

class BuildsTask extends BaseTask
{
    protected string $title = 'BuildsTask';

    protected static string $description = 'Fetch CI builds';

    protected string $type = 'builds';
}
