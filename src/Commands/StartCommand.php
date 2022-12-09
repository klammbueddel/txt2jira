<?php

namespace App\Commands;

use App\Controller;
use App\Model\Alias;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('start');
        $this->setDescription('Starts / stops a log');

        $this->addArgument('issue', InputArgument::OPTIONAL, 'Name of the issue');
        $this->addArgument('comments', InputArgument::IS_ARRAY, 'Name of the issue');
        $this->addOption('time', 't', InputOption::VALUE_OPTIONAL, 'Alternative start time');
        $this->addOption('continue', 'c', InputOption::VALUE_NEGATABLE, 'Use last end time as start time.');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $logs = $controller->logs();

        $issue = $input->getArgument('issue');
        $comments = $input->getArgument('comments');
        $time = $input->getOption('time');
        $continue = $input->getOption('continue');

        if ($continue) {
            $time = $controller->lastTime();
        }

        # extract issue number from branch name or link
        if ($issue && preg_match('/([A-Z]{2,}-[0-9]+)/', $issue, $matches)) {
            $issue = $matches[1];
        }

        $comments = join(' ', $comments);

        $transientLogs = array_values(
            array_filter($logs, function (Log $log) {
                return $log->transient;
            })
        );

        if ($transientLogs) {
            if ($issue && $transientLogs[0]?->issues[0]?->alias === $issue) {
                $output->writeln('Running since ' . $transientLogs[0]->start->format('H:i'));
            } else {
                $controller->stop($time);

                if ($issue) {
                    $controller->start($issue, $comments, $time);
                }
            }

        } else {
            $controller->start($issue, $comments, $time);
        }

        return 0;
    }
}
