<?php

namespace App;

use Ahc\Cli\Input\Command;
use Ahc\Cli\Output\Color;
use RuntimeException;

class InitCommand extends Command
{

    public function __construct(private readonly Config $config)
    {
        parent::__construct('init', 'Setup configuration');

        $this
            ->option('-c --change', 'Change existing configuration')
            ->option('-H --host', 'Jira host')
            ->option('-u --user', 'Jira user')
            ->option('-t --token', 'Jira token')
            ->option('-f --file', 'Log file');

        $this->set('host', $this->config->host ?? null);
        $this->set('user', $this->config->user ?? null);
        $this->set('token', $this->config->token ?? null);
        $this->set('file', $this->config->file ?? null);
    }

    public function execute($host, $user, $token, $file, $change)
    {
        $io = $this->app()->io();

        if (!$host || $change) {
            $host = $io->prompt('Enter Jira host', $host ?? 'mycompany.atlassian.net');
        }
        if (!$user || $change) {
            $user = $io->prompt('Enter Jira user', $user);
        }
        if (!$token || $change) {
            $token = $io->prompt('Enter Jira api token (https://id.atlassian.com/manage/api-tokens)', $token);
        }
        if (!$file || $change) {
            $file = $io->prompt('Enter path to log file', $file ?? 'log.txt');
        }

        $color = new Color();
        $io->write('Host  '.$color->ok($host), true);
        $io->write('User  '.$color->ok($user), true);
        $io->write('Token '.$color->ok($token), true);
        $io->write('File  '.$color->ok($file), true);

        $color = new Color();
        $client = new JiraClient($this->config);
        try {
            $io->write($color->comment('Verify configuration...'), true);
            $client->getCurrentUser();

            if (!file_exists($file)) {
                throw new RuntimeException("$file not found");
            }

            $io->write($color->ok('Configuration verified ✓'), true);
            $this->config->save();
        } catch (\Exception $ex) {
            $io->write($color->error('❌ '.$ex->getMessage()), true);

            $action =
                $io->choice(
                    'What next?',
                    [
                        's' => 'Save configuration',
                        'e' => 'Edit configuration',
                        'q' => 'Quit without saving',
                    ],
                    'e'
                );
            switch ($action) {
                case 's':
                    $this->config->host = $host;
                    $this->config->user = $user;
                    $this->config->token = $token;
                    $this->config->file = $file;
                    $this->config->save();
                    break;
                case 'e':
                    $this->execute($host, $user, $token, $file, $change);;
                    break;
            }
        }
    }
}
