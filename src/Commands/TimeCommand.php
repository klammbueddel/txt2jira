<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TimeCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('time');
        $formats = [
            '<hour>:<minute>' => 'set time',
            '<minute>       ' => 'set minutes in current hour',
            '+<minutes>     ' => 'add minutes',
            '_<minutes>     ' => 'subtract minutes',
        ];
        $this->setDescription('Edit time (start/stop) of current log');
        $this->setHelp(
            "Supported formats:\n".implode(
                "\n",
                array_map(fn($format, $description) => "  $format - $description", array_keys($formats), $formats)
            )
        );
        $this->addArgument('time', InputArgument::OPTIONAL, 'Time');
        $this->addOption('break', 'b', InputOption::VALUE_NEGATABLE, 'Detach from last start time which might add a break');
        $this->addOption('start', 's', InputOption::VALUE_NEGATABLE, 'Edit start time');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $time = $input->getArgument('time');
        $break = $input->getOption('break');
        $start = $input->getOption('start');

        $controller->editTime($time, $break, $start);

        return 0;
    }
}
