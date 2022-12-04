<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EditCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('edit');
        $this->setDescription('Edit time (start/stop) of current log');
        $this->addArgument('time', InputArgument::OPTIONAL, 'Time');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $time = $input->getArgument('time');

        $controller->editTime($time);

        return 0;
    }
}
