<?php

namespace App\Commands;

use App\Config;
use App\Controller;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{

    abstract function exec(Controller $controller, InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config();
        $config->load('~/.txt2jira');
        $controller = new Controller($config);
        $controller->setIo($input, $output, $this->getHelper('question'));

        return $this->exec($controller, $input, $output);
    }

}
