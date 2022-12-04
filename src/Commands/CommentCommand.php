<?php

namespace App\Commands;

use App\Controller;
use App\Model\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommentCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('comment');

        $this->setDescription('Change comment of current log');
        $this->addArgument('comment', InputArgument::IS_ARRAY, 'Comment');
        $this->addOption('append', 'a', InputOption::VALUE_NEGATABLE, 'Append to existing comment', false);
    }

    public function exec(Controller $controller, InputInterface $input, OutputInterface $output): int
    {
        $append = $input->getOption('append');
        $comment = join(' ', $input->getArgument('comment'));

        $controller->comment($append, $comment);

        return 0;
    }
}
