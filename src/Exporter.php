<?php

namespace App;

use App\Model\Issue;
use App\Model\Log;
use App\Model\Pause;
use DateInterval;
use Exception;

class Exporter
{

    public function __construct(
        private readonly JiraClient $client,
    ) {
    }

    public function export(Interactor $io, $items, callable $onChange = null)
    {
        foreach ($items as $item) {
            $io->write('Log '.self::formatJiraTime($item['minutes'] * 60).' '.$item['issue'].' '.$item['comment']);

            try {
                $this->client->addWorkLog($item['issue'], $item['comment'], $item['date'], $item['minutes']);
                $io->success(' ✓');

                foreach ($item['issues'] as $issue) {
                    /* @var $issue Issue|Pause */
                    $issue->isDone = true;

                    if ($onChange) {
                        $onChange($issue);
                    }
                }
            } catch (HttpException $ex) {
                $json = json_decode($ex->getMessage(), true);
                $error = $json['errorMessages'][0] ?? $ex->getMessage();
                $io->error(' ❌ '.$error);
            }
        }
    }

    /**
     * @param array $logs
     * @return array
     */
    public function aggregateUncommitedLogs(array $logs): array
    {
        $result = [];
        $logsByDay = [];

        foreach ($logs as $log) {
            /** @var Log $log */
            $logsByDay[$log->start->format('Y-m-d')][] = $log;
        }

        foreach ($logsByDay as $day => $logs) {
            $logsByIssue = [];
            foreach ($logs as $log) {
                /** @var Log $log */

                if ($log->issues && $log->issues[0] instanceof Issue) {
                    $logsByIssue[$log->issues[0]->issue][] = $log;
                }

                # collect child logs
                foreach ($log->children as $child) {
                    if ($child->issues && $child->issues[0] instanceof Issue) {
                        $logsByIssue[$child->issues[0]->issue][] = $child;
                    }
                }
            }

            foreach ($logsByIssue as $issue => $logs) {
                if (!$issue) {
                    continue;
                }
                $total = 0;
                $minutes = 0;
                $start = null;
                $end = null;
                $comments = [];
                $chargedIssues = [];
                $transient = false;

                foreach ($logs as $log) {

                    if ($log->transient) {
                        $transient = true;
                    }

                    foreach ($log->issues as $idx => $issueNode) {
                        /** @var $issueNode Issue */

                        if ($issueNode->isDone) {
                            continue;
                        }

                        if ($idx === 0) {
                            $start = $start ?: $log->start;
                            $end = $log->end ?: (clone $start)->add(new DateInterval('P1D'))->setTime(0, 0);
                            $minutes += $log->getMinutes();
                            $chargedIssues[] = $issueNode;
                        }

                        # add possible additional comments
                        $comments[] = $idx === 0 ? $issueNode->comment : $issueNode->input;
                    }

                    $total += $log->getMinutes();
                }
                if ($minutes > 0 || $transient) {
                    $result[] = [
                        'date' => $start,
                        'end' => $end,
                        'issue' => $issue,
                        'minutes' => $minutes,
                        'total' => $total,
                        'comment' => self::sanitizeComments($comments),
                        'issues' => $chargedIssues,
                        'transient' => $transient,
                    ];
                }
            }
        }

        return $result;
    }

    public function serialize(Log $log)
    {

        $result = [];
        $currentTime = $log->start;

        foreach ($log->children as $child) {
            if ($child->start < $currentTime) {
                throw new SyntaxError('Start time of '.$child.' is before '.$currentTime->format('H:i'));
            }

            if ($child->start > $currentTime) {
                $result[] = new Log($currentTime, $log->issues, $child->start);
            }

            $result[] = $child;
            $currentTime = $child->end;
        }

        if ($currentTime < $log->end) {
            $result[] = new Log($currentTime, $log->issues, $log->end, [], $log->transient);
        }

        return $result;
    }

    /**
     * @param array $logs
     * @return array
     * @throws Exception
     */
    public function chargeableItems(array $logs, $all = false): array
    {
        $result = [];
        $logsByDay = [];

        foreach ($logs as $log) {

            if ($log->children) {
                foreach ($this->serialize($log) as $log) {
                    $logsByDay[$log->start->format('Y-m-d')][] = $log;
                }

            } else {
                /** @var Log $log */
                $logsByDay[$log->start->format('Y-m-d')][] = $log;
            }
        }

        foreach ($logsByDay as $day => $logs) {

            foreach ($logs as $log) {
                $total = 0;
                $minutes = 0;
                $start = null;
                $end = null;
                $comments = [];
                $issues = [];
                $transient = $log->transient;
                $issue = $log->issues[0]->issue ?? null;

                foreach ($log->issues as $idx => $issueNode) {
                    /** @var $issueNode Issue */

                    if (! $all && $issueNode->isDone) {
                        continue;
                    }
                    if ($issueNode instanceof Pause) {
                        continue;
                    }

                    if ($idx === 0) {
                        $start = $start ?: $log->start;
                        $end = $log->end ?: (clone $start)->add(new DateInterval('P1D'))->setTime(0, 0);
                        if (!$issueNode->isDone) {
                            $minutes += $log->getMinutes();
                        }
                        $issues[] = $issueNode;
                    }

                    $comments[] = $idx === 0 ? $issueNode->comment : $issueNode->input;
                    $total += $log->getMinutes();
                }

                if ($start) {
                    $result[] = [
                        'date' => $start,
                        'end' => $end,
                        'issue' => $issue,
                        'minutes' => $minutes,
                        'total' => $total,
                        'comment' => self::sanitizeComments($comments),
                        'issues' => $issues,
                        'transient' => $transient,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @param array $logs
     * @param bool $filterOpen
     * @return array
     * @throws Exception
     */
    public function aggregateAllLogs(array $logs): array
    {
        $result = [];
        $logsByDay = [];

        foreach ($logs as $log) {
            /** @var Log $log */
            $logsByDay[$log->start->format('Y-m-d')][] = $log;
        }

        foreach ($logsByDay as $day => $logs) {
            $logsByIssue = [];
            foreach ($logs as $log) {
                /** @var Log $log */

                if ($log->issues && $log->issues[0] instanceof Issue) {
                    $logsByIssue[$log->issues[0]->issue][] = $log;
                }

                # collect child logs
                foreach ($log->children as $child) {
                    if ($child->issues && $child->issues[0] instanceof Issue) {
                        $logsByIssue[$child->issues[0]->issue][] = $child;
                    }
                }
            }

            foreach ($logsByIssue as $issue => $logs) {
                if (!$issue) {
                    continue;
                }
                $total = 0;
                $minutes = 0;
                $start = null;
                $end = null;
                $comments = [];
                $issues = [];
                $transient = false;

                foreach ($logs as $log) {

                    if ($log->transient) {
                        $transient = true;
                    }

                    foreach ($log->issues as $idx => $issueNode) {
                        /** @var $issueNode Issue */

                        if ($idx === 0) {
                            $start = $start ?: $log->start;
                            $end = $log->end ?: $end;
                            if (!$issueNode->isDone) {
                                $minutes += $log->getMinutes();
                            }
                            $issues[] = $issueNode;
                        }

                        # add possible additional comments
                        $comments[] = $idx === 0 ? $issueNode->comment : $issueNode->input;
                    }

                    $total += $log->getMinutes();
                }
                if ($total > 0 || $transient) {
                    $result[] = [
                        'date' => $start,
                        'end' => $end,
                        'issue' => $issue,
                        'minutes' => $minutes,
                        'total' => $total,
                        'comment' => self::sanitizeComments($comments),
                        'issues' => $issues,
                        'transient' => $transient,
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
        $explode = explode('; ', implode('; ', $comments));
        $explode = array_filter($explode, fn($x) => !!trim($x));
        foreach ($explode as $idx => $item) {
            $explode[$idx] = trim($explode[$idx]);
        }

        return implode('; ', array_unique($explode));
    }

    public static function formatJiraTime(int $s, $strPad = 7)
    {
        if ($s < 0) {
            return '-'.self::formatJiraTime(abs($s));
        }
        $h = floor($s / 3600);
        $s -= $h * 3600;
        $m = floor($s / 60);

        $items = [];
        if ($h) {
            $items[] = "{$h}h";
        }
        if ($m) {
            $items[] = "{$m}m";
        }

        return str_pad(implode(' ', $items), $strPad, ' ');
    }
}
