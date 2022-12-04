<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use App\Renderer;
use Symfony\Component\Console\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommitCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('commit');

        $this->setDescription('Commit to Jira');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $controller->commitToJira();

        return 0;
    }
}
