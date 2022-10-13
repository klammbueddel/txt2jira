<?php

namespace App;

use Ahc\Cli\Input\Command;
use Ahc\Cli\Output\Color;

class InitCommand extends Command
{

    public function __construct()
    {
        parent::__construct('init', 'Setup configuration');

        $this
            ->option('-c --change', 'Change existing configuration')
            ->option('-H --host', 'Jira host')
            ->option('-u --user', 'Jira user')
            ->option('-t --token', 'Jira token')
            ->option('-f --file', 'Log file');

        if (file_exists(__DIR__ ."/../config.json")) {
            $config = json_decode(file_get_contents(__DIR__ ."/../config.json"), true);

            $this->set('host', $config['host'] ?? null);
            $this->set('user', $config['user'] ?? null);
            $this->set('token', $config['token'] ?? null);
            $this->set('file', $config['file'] ?? null);
        }
    }

    public function execute($host, $user, $token, $file, $change)
    {
        $io = $this->app()->io();

        if (!$host || $change) {
            $host = $io->prompt('Enter Jira host', $host ?? 'https://mycompany.atlassian.net');
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

        file_put_contents(
            __DIR__ ."/../config.json",
            json_encode([
                'host' => $host,
                'user' => $user,
                'token' => $token,
                'file' => $file,
            ])
        );
    }
}
