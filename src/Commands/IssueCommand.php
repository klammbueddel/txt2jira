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
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $controller->changeIssue();

        return 0;
    }
}
