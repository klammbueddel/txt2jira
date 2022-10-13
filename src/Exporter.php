<?php

namespace App;

use Ahc\Cli\IO\Interactor;
use DateTime;

class Exporter
{

    public function __construct(private ?JiraClient $client, private ?Interactor $interactor) { }

    public function export($logs)
    {
        foreach ($logs as $log) {
            if ($log['comment']) {
                $this->interactor->comment(
                    'Log '.self::formatJiraTime($log['minutes'] * 60).' '.$log['issue'].' '.$log['comment']
                );

                $this->client->addWorkLog($log['issue'], $log['comment'], $log['date'], $log['minutes']);

                $this->interactor->greenBold(' ✓', true);

                foreach ($log['items'] as $item) {
                    $item->markDone();
                }
            }
        }
    }

    /**
     * @param Item[] $items
     */
    public function prepare(array $days, $filterOpen = true): array
    {
        $result = [];
        foreach ($days as $date => $_items) {

            $itemsPerIssue = [];
            foreach ($_items as $item) {
                /* @var Item $item */
                $itemsPerIssue[$item->getIssue()][] = $item;
            }

            foreach ($itemsPerIssue as $issue => $items) {
                if (!$issue) {
                    continue;
                }
                $total = 0;
                $minutes = 0;
                $start = null;
                $comments = [];
                $allComments = [];
                $chargeItems = [];
                $alias = null;
                foreach ($items as $item) {

                    if (!$item->isDone()) {
                        $start = $start ?: $item->getStart();
                        $minutes += $item->getMinutes();
                        $chargeItems[] = $item;
                        $comments[] = $item->getComment();
                    }
                    $total += $item->getMinutes();
                    $allComments[] = $item->getComment();
                    $alias = $item->getAlias();
                }
                if ($minutes > 0 || !$filterOpen) {
                    $result[] = [
                        'date' => new DateTime($date.' '.$start),
                        'issue' => $issue,
                        'alias' => $alias,
                        'minutes' => $minutes,
                        'total' => $total,
                        'comment' => self::sanitizeComments($comments),
                        'allComments' => self::sanitizeComments($allComments),
                        'items' => $chargeItems,
                    ];
                }
            }
        }

        return $result;
    }

    public static function sanitizeComments(array $comments): ?string
    {
        if (!count($comments)) {
            return null;
        }
        $explode = explode(', ', implode(', ', $comments));
        $explode = array_filter($explode, fn($x) => !!trim($x));
        foreach ($explode as $idx => $item) {
            $explode[$idx] = trim($explode[$idx]);
        }

        return implode(', ', array_unique($explode));
    }

    public static function formatJiraTime(int $s, $strPad = 7)
    {
        $h = floor($s / 3600);
        $s -= $h * 3600;
        $m = floor($s / 60);
        $s -= $m * 60;

        $items = [];
        if ($h) {
            $items[] = "{$h}h";
        }
        if ($m) {
            $items[] = "{$m}m";
        }
        if ($s) {
            $items[] = "{$s}s";
        }

        return str_pad(implode(' ', $items), $strPad, ' ');
    }
}
