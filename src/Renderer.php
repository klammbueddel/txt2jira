<?php

namespace App;

use DateTime;
use Symfony\Component\Console\Color;

class Renderer
{
    const SUMMARY_WIDTH = 50;
    const ISSUE_WIDTH = 10;

    public function __construct(private readonly ?Importer $importer = null) { }

    public function renderLog($log, $breaks, $isLast)
    {
        # state column
        if ($log['transient']) {
            $line = '⏵ ';
        } else {
            if ($log['minutes'] > 0) {
                if ($log['minutes'] != $log['total']) {
                    $line = '~ ';
                } else {
                    $line = '* ';
                }
            } else {
                $line = '  ';
            }
        }

        $issue = $log['issues'][0];

        $line = $line.$this->getTime($log, $breaks, $isLast)
            .'  '.$this->getSummary($issue)
            .'  '.$this->minutes($log)
            .' '.str_pad($log['comment'], 35, ' ');

        return $log['transient'] ? self::colorize($line, 'bright-green') : $line;
    }

    public function render(array $logs, $breaks = false): string
    {
        if (! $logs) {
            return ' --- list is empty --- ';
        }
        $lines = [];

        $lastLog = null;
        $total = 0;
        foreach ($logs as $idx => $log) {
            $isLast = $idx === count($logs) - 1;

            # sum row
            if ($lastLog && $lastLog['date']->format('d.m.y') !== $log['date']->format('d.m.y')) {
                $this->renderSum($lines, $total);
                $total = 0;
            }

            # date headline
            if (!$lastLog || $lastLog['date']->format('d.m.y') !== $log['date']->format('d.m.y')) {
                $newDay = true;
                $lines[] = "-------------------------------------------------- "
                    .$this->colorize($log['date']->format('d.m.y'), 'bright-yellow')
                    ." -----------------------------------------------------";
            } else {
                $newDay = false;
            }

            # render break
            if ($breaks && !$newDay && $lastLog && $lastLog['end'] != $log['date']) {
                $break = str_pad('', 7, ' ')
                    . JiraDateInterval::formatSeconds($log['date']->getTimestamp() - $lastLog['end']->getTimestamp());
                $lines[] = self::colorize($break, 'yellow');
            }

            $lines[] = $this->renderLog($log, $breaks, $isLast);

            $total += $log['total'];
            $lastLog = $log;
        }

        # render current break
        if ($breaks && $lastLog && $lastLog['end'] && $lastLog['date']->format('d.m.y') === (new DateTime())->format(
                'd.m.y'
            ) && $log && !$log['transient']) {
            $seconds = (new DateTime())->getTimestamp() - $lastLog['end']->getTimestamp();
            if ($seconds > 60) {
                $break = str_pad('', 7, ' ')
                    .JiraDateInterval::formatSeconds((new DateTime())->getTimestamp() - $lastLog['end']->getTimestamp());
                $lines[] = self::colorize($break, 'yellow');
            }
        }

        $this->renderSum($lines, $total);

        return implode("\n", $lines);
    }

    private function colorize($value, $color)
    {
        return (new Color($color))->apply($value);
    }

    private function renderSum(&$lines, $total)
    {
        $lines[] =
            "----------------------------------------------------- "
            .str_pad($this->colorize(JiraDateInterval::formatMinutes($total, 0), 'green').' ', 69, '-');
        $lines[] = '';
    }

    /**
     * @param mixed $issue
     * @return string
     */
    private function getSummary(mixed $issue): string
    {
        $summary = $this->importer?->getSummary($issue->issue) ?:
            ($issue->alias !== $issue->issue ? $issue->alias : '');

        if ($summary && strlen($summary) > self::SUMMARY_WIDTH) {
            $summary = substr($summary, 0, self::SUMMARY_WIDTH - 3).'...';
        }

        $summary =
            str_pad($issue->issue, self::ISSUE_WIDTH, ' ')
            .' '.str_pad($summary, self::SUMMARY_WIDTH, ' ').' ';

        return $summary;
    }

    /**
     * @param $log
     * @param $breaks
     * @param $isLast
     * @return string
     */
    private function getTime($log, $breaks, $isLast): string
    {
        $end = $log['end']->format('H:i');

        if ($breaks && $isLast && !$log['transient']) {
            $end = self::colorize($end, 'cyan');
        }

        return $log['date']->format('H:i').' - '.$end;
    }

    /**
     * @param $log
     * @return string
     */
    private function minutes($log): string
    {
        return JiraDateInterval::formatMinutes($log['total'] ?: $log['minutes']);
    }

}
