<?php

namespace App;

class Renderer
{

    public function __construct() { }

    public function render(array $logs, $days): string
    {
        $lines = [];

        $lastDay = null;
        $total = 0;
        foreach ($logs as $log) {

            # sum row
            if ($lastDay && $lastDay !== $log['date']->format('d.m.y')) {
                $this->renderSum($lines, $total);
                $total = 0;
            }

            # date headline
            if (!$lastDay || $lastDay !== $log['date']->format('d.m.y')) {
                if ($days-- <= 0) {
                    break;
                }
                $lines[] = "------------------------------------ "
                    .$this->colorize($log['date']->format('d.m.y'), 'Yellow')
                    ." ------------------------------------";
            }

            # state column
            if ($log['minutes'] > 0) {
                if ($log['minutes'] != $log['total']) {
                    $line = '~ ';
                } else {
                    $line = '* ';
                }
            } else {
                $line = '  ';
            }

            $line .= (str_pad(trim($log['alias'], '_'), 10, ' ') ?: '?????????').' '.Exporter::formatJiraTime(
                    $log['total'] * 60
                ).' '.str_pad($log['allComments'], 80, ' ');

            if ($log['minutes'] > 0 && $log['minutes'] !== $log['total']) {
                $line .= '* +'.Exporter::formatJiraTime($log['minutes'] * 60).' '.$log['comment']."";
            }
            $lines[] = $line;
            $total += $log['total'] * 60;
            $lastDay = $log['date']->format('d.m.y');
        }

        if ($days > 0) {
            $this->renderSum($lines, $total);
        }

        return implode("\n", $lines);
    }

    private static $colors = [
        'Black' => '0;30',
        'Dark Gray' => '1;30',
        'Red' => '0;31',
        'Light Red' => '1;31',
        'Green' => '0;32',
        'Light Green' => '1;32',
        'Brown/Orange' => '0;33',
        'Yellow' => '1;33',
        'Blue' => '0;34',
        'Light Blue' => '1;34',
        'Purple' => '0;35',
        'Light Purple' => '1;35',
        'Cyan' => '0;36',
        'Light Cyan' => '1;36',
        'Light Gray' => '0;37',
        'White' => '1;37',
    ];

    private function colorize($value, $color)
    {
        $code = self::$colors[$color];

        return "\033[{$code}m$value\033[0m";
    }

    private function renderSum(&$lines, $total)
    {
        $lines[] =
            "-------------------------------------- "
            .str_pad($this->colorize(Exporter::formatJiraTime($total, 0), 'Green') . ' ', 54, '-');
        $lines[] = '';
    }

}
