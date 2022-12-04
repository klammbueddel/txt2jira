<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use App\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('delete');
        $this->setDescription('Delete current log');
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $controller->delete();

        return 0;
    }
}
