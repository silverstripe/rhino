<?php

namespace App\Tasks;

class ReleasesTask extends BaseTask
{
    protected string $title = 'ReleasesTask';

    protected static string $description = 'Fetch releases';

    protected string $type = 'releases';
}
