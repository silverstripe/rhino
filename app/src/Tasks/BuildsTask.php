<?php

namespace App\Tasks;

use App\Misc\BuildsPipeline;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class BuildsTask extends BaseTask
{
    protected string $title = 'BuildsTask';

    protected static string $description = 'Fetch CI builds';

    protected string $type = 'builds';

    /**
     * Runs the builds pipeline with the refetch option from the CLI input.
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $refetch = (bool) $input->getOption('refetch');
        $pipeline = new BuildsPipeline();
        $pipeline->run($refetch);
        return Command::SUCCESS;
    }
}
