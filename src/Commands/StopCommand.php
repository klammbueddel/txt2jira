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

class StopCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('stop');
        $this->setDescription('Stop active log');
        $this->addOption('time', 't', InputOption::VALUE_OPTIONAL, 'Alternative end time');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $time = $input->getOption('time');
        $controller->stop($time);

        return 0;
    }
}
