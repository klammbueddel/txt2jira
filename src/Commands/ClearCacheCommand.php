<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCacheCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('clear-cache');

        $this->setDescription('Clears the cache');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $controller->clearCache();

        return 0;
    }
}
