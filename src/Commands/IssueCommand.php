<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IssueCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('issue');

        $this->setDescription('Change issue of current log');
        $this->addArgument('issue', InputArgument::IS_ARRAY, 'Name of the issue');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('issue');

        $issue = array_shift($args);

        if ($issue && preg_match('/([A-Z]{2,}-[0-9]+)/', $issue, $matches)) {
            $issue = $matches[1];
        }

        $comments = join(' ', $args);

        $controller->changeIssue($issue, $comments);

        return 0;
    }
}
