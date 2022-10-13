<?php

namespace App;

use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Ahc\Cli\Output\Color;
use RuntimeException;

class ExecuteCommand extends Command
{

    public function __construct()
    {
        parent::__construct('commit', 'Commit you work logs...');

        $this
            ->argument('file', 'path to your log file')
            ->option('-a --all', 'Show all')
            ->option('-d|--days [days]', 'Show last x days', 'intval', 5);

        if (file_exists(__DIR__ ."/../config.json")) {
            $config = json_decode(file_get_contents(__DIR__ ."/../config.json"), true);

            $this->set('host', $config['host'] ?? null);
            $this->set('user', $config['user'] ?? null);
            $this->set('token', $config['token'] ?? null);
        }
    }

    public function execute($file, $all, $days)
    {
        $interactor = $this->app()->io();
        if (file_exists(__DIR__ ."/../config.json")) {
            $config = json_decode(file_get_contents(__DIR__ ."/../config.json"), true);
        } else {
            $interactor->greenBold('Please setup configuration with `txt2jira init`', true);

            return 1;
        }

        $path = $file ?? $config['file'];

        $import = new Importer();
        if (!file_exists($path)) {
            throw new RuntimeException("$path not found");
        }

        $in = file_get_contents($path);
        $items = $import->parse($in);

        $exporter = new Exporter(new JiraClient($config), $interactor);
        $logs = $exporter->prepare($items, !$all);

        $diff = $import->diff($in);
        if ($diff) {
            echo $diff;
        }
        echo "\n";
        echo (new Renderer())->render($logs, $days);

        if (!$interactor->confirm('Commit ?', 'n')) {
            return 1;
        }

        $exporter->export($logs);
        $interactor->greenBold('All done!', true);
        $import->export($path, $items);

        return 0;
    }

}
