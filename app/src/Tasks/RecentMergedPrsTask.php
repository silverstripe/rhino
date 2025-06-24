<?php

namespace App\Tasks;

class RecentMergedPrsTask extends BaseTask
{
    protected string $title = 'RecentMergedPrsTask';

    protected static string $description = 'Fetch recently merged PRs';

    protected string $type = 'merged-prs';
}
