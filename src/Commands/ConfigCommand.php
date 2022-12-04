<?php

namespace App\Commands;

use App\Config;
use App\Interactor;
use App\JiraClient;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends Command
{

    public function __construct()
    {
        parent::__construct('config');
        $this->setDescription('Setup configuration');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new Interactor($input, $output, $this->getHelper('question'));
        $config = new Config();

        try {
            $config->load('~/.txt2jira');
        } catch (RuntimeException $e) {
            $io->writeln('No config file found, creating new one');
        }

        $config->host = $io->prompt('Enter Jira host', $config->host ?: 'mycompany.atlassian.net');
        $config->user = $io->prompt('Enter Jira user', $config->user);
        $config->token = $io->prompt(
            'Enter Jira api token (https://id.atlassian.com/manage/api-tokens)',
            $config->token
        );
        $config->file = $io->prompt('Enter path to log file', $config->getFile() ?: 'log.txt');

        $config->save();

        $client = new JiraClient($config);
        try {
            $io->info('Verify configuration...');
            $client->getCurrentUser();

            if (!file_exists($config->getFile())) {
                throw new RuntimeException("{$config->getFile()} not found");
            }

            $io->success('Configuration verified ✓');
        } catch (\Exception $ex) {
            $io->error('❌ '.$ex->getMessage());
        }

        return 0;
    }
}
