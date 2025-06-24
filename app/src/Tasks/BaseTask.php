<?php

namespace App\Tasks;

use App\Misc\Runner;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\ClassInfo;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class BaseTask extends BuildTask
{
    protected string $title = 'BaseTask';

    protected static string $description = 'Base task for other tasks';

    protected string $type = 'base';

    public static function getName(): string
    {
        $shortName = ClassInfo::shortName(new static);
        return "tasks:$shortName";
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $refetch = (bool) $input->getOption('refetch');
        Runner::singleton()->run($this->type, $refetch);
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('refetch', null, InputOption::VALUE_OPTIONAL, 'Refetch data from API'),
        ];
    }
}
