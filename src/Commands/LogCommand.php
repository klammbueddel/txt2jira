<?php

namespace App\Commands;

use App\Controller;
use App\JiraDateInterval;
use App\Model\Alias;
use App\Model\Log;
use App\SyntaxError;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('log');
        $this->setDescription('Adds a log');

        $this->addArgument('time', InputArgument::OPTIONAL, 'Time of the issue');
        $this->addArgument('duration', InputArgument::OPTIONAL, 'Duration of the issue');
        $this->addArgument('issue', InputArgument::OPTIONAL, 'Name of the issue');
        $this->addArgument('comments', InputArgument::IS_ARRAY, 'Comment');
        $this->addOption('continue', 'c', InputOption::VALUE_NEGATABLE, 'Use last end time as start time.');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $logs = $controller->logs();

        $arguments = $input->getArgument('comments');
        if ($input->getArgument('issue')) {
            array_unshift($arguments, $input->getArgument('issue'));
        }
        if ($input->getArgument('duration')) {
            array_unshift($arguments, $input->getArgument('duration'));
        }
        if ($input->getArgument('time')) {
            array_unshift($arguments, $input->getArgument('time'));
        }

        # normalize arguments (for easier tests)
        $arguments = explode(' ', implode(' ', $arguments));
        $issue = array_shift($arguments);

        if ($time = $controller->tryParseTimeNode($issue)) {
            $time = $time->time;
            $issue = array_shift($arguments);
        }

        $duration = 0;
        while ($minutes = JiraDateInterval::parse($issue ?: '')->getMinutes()) {
            $duration += $minutes;
            $issue = array_shift($arguments);
        }

        if ($input->getOption('continue')) {
            $time = $controller->lastTime();
        }

        # extract issue number from branch name or link
        if ($issue && preg_match('/([A-Z]{2,}-[0-9]+)/', $issue, $matches)) {
            $issue = $matches[1];
        }

        $comments = join(' ', $arguments);

        $transientLogs = array_values(
            array_filter($logs, function (Log $log) {
                return $log->transient;
            })
        );

        if ($transientLogs) {
            if ($issue && $transientLogs[0]?->issues[0]?->alias === $issue) {
                $output->writeln('Running since '.$transientLogs[0]->start->format('H:i'));
            } else {
                $controller->stop($time, $duration);

                if ($issue) {
                    $controller->start($issue, $comments, $time, $duration);
                }
            }

        } else {
            $controller->start($issue, $comments, $time, $duration);
        }

        return 0;
    }
}
