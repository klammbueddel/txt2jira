<?php

namespace App\Commands;

use App\Config;
use App\Controller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Config file');
    }

    abstract function exec(Controller $controller, InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $input->getOption('file') ?? '~/.txt2jira';

        $config = new Config();
        $config->load($configFile);
        $controller = new Controller($config);
        $controller->setIo($input, $output, $this->getHelper('question'));

        return $this->exec($controller, $input, $output);
    }

}
