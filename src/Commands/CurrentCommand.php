<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use App\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CurrentCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('current');

        $this->setDescription('Show current log');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $chargableItems = $controller->chargeableItems();

        $output->writeln((new Renderer())->renderLog(end($chargableItems), false, false));

        return 0;
    }
}
