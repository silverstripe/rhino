<?php

namespace App\Tasks;

class MergeUpsTask extends BaseTask
{
    protected string $title = 'MergeUpsTask';

    protected static string $description = 'Fetch merge-up status';

    protected string $type = 'merge-ups';
}
