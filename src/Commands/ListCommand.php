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

class ListCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('list');
        $this->setDescription('List uncommitted logs');
        $this->addOption('all', 'a', InputOption::VALUE_NEGATABLE, 'List all logs');
        $this->addOption('combine', 'c', InputOption::VALUE_NEGATABLE, 'Combine logs with same issue');
        $this->addOption('breaks', 'b', InputOption::VALUE_NEGATABLE, 'Show breaks');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $all = $input->getOption('all') ?? false;
        $combine = $input->getOption('combine') ?? false;
        $controller->setIo($input, $output, $this->getHelper('question'));
        $breaks = $input->getOption('breaks');

        $controller->load();

        if ($all) {
            if ($breaks === true) {
                throw new \Exception('breaks are not supported in all mode');
            }
            $breaks = false;
            $chargableItems = $controller->aggregateAllLogs();
        } else if ($combine) {
            if ($breaks === true) {
                throw new \Exception('breaks are not supported in all mode');
            }
            $breaks = false;
            $chargableItems = $controller->aggregateUncommittedLogs();
        } else {
            $chargableItems = $controller->chargeableItems();
            $breaks = $breaks ?? true;
        }
        $output->writeln($controller->getRenderer()->render($chargableItems, $breaks));

        return 0;
    }
}
