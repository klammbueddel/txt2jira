<?php

namespace App;

use App\Model\Alias;
use App\Model\Day;
use App\Model\Issue;
use App\Model\Log;
use App\Model\Minutes;
use App\Model\Node;
use App\Model\Time;
use DateInterval;
use DateTime;
use Exception;
use Jfcherng\Diff\DiffHelper;

class Interpreter
{

    /**
     * @param Day $day
     * @param Time $time
     * @return Log
     * @throws Exception
     */
    public function standaloneLog(Node $root, Day $day, Time $time): Log
    {
        $minute = $time->getOneByType(Minutes::class);
        $issue = $minute->children[0];

        $start = new DateTime($day->date.' '.$time->time);
        $end = new DateTime($day->date.' '.$time->time);
        $end->add(new DateInterval('PT'.$minute->minutes.'M'));

        return new Log(
            $start,
            [$this->resolveAliases($issue, $day)],
            $end,
        );
    }

    /**
     * @param Node $root
     * @return array Logs
     * @throws SyntaxError
     */
    public function getLogs(Node $root): array
    {
        $logs = [];
        $currentLog = null;

        foreach ($root->children as $node) {
            if ($node instanceof Day) {
                $day = $node;

                if (!$this->validateDate($day->date)) {
                    continue;
                }

                foreach ($node->children as $childNode) {

                    if ($childNode instanceof Time) {
                        if ($childNode->children) {
                            $standalone = $this->standaloneLog($root, $day, $childNode);
                            if ($currentLog && $currentLog->issues) {
                                $currentLog->addChild($standalone);
                            } else {
                                $logs[] = $standalone;
                            }
                            continue;
                        }

                        if (!$currentLog) {
                            $currentLog = new Log(new DateTime($day->date.' '.$childNode->time));
                            $logs[] = $currentLog;
                        } else {
                            $currentLog->end = new DateTime($day->date.' '.$childNode->time);
                            if ($currentLog->end < $currentLog->start) {
                                $currentLog->end->add(new DateInterval('P1D'));
                            }

                            $currentLog = new Log(new DateTime($day->date.' '.$childNode->time));
                            $logs[] = $currentLog;
                        }
                    }

                    if ($childNode instanceof Issue) {
                        if (!$currentLog) {
                            throw new SyntaxError('Could not find start time of '.$childNode->input);
                        }
                        $currentLog->issues[] = $this->resolveAliases($childNode, $day);
                    }
                }

            }
        }
        if ($currentLog && $currentLog->issues && !$currentLog->end) {
            $currentLog->end = max(new DateTime(), $currentLog->start);;
            $currentLog->transient = true;
        }

        # filter logs with start and end time
        return array_values(
            array_filter($logs, function (Log $log) {
                return $log->start && $log->end;
            })
        );
    }

    /**
     * Resolve aliases in issue
     * @param Node $node
     * @param Day $day
     * @return Node
     */
    public function resolveAliases(Node $node, Day $day): Node
    {
        if ($node instanceof Issue) {
            $map = $this->aliases($day);

            $firstWord = explode(' ', $node->input)[0];
            $rest = substr($node->input, strlen($firstWord) + 1);

            // resolve issue by alias
            $resolved = $map[$firstWord] ?? $firstWord;

            // resolve alias by issue
            $alias = array_search($resolved, $map) ?: $resolved;

            $node->alias = $alias;
            $node->issue = $resolved;
            $node->comment = $rest;
        }

        return $node;
    }

    /**
     * @param Day $day
     * @return Alias[]
     */
    public function aliases(Day $day): array
    {
        $aliases = array_merge($day->parent->getByType(Alias::class, false), $day->getByType(Alias::class, false));
        $map = [];

        foreach ($aliases as $alias) {
            /** @var Alias $alias */
            $map[$alias->alias] = $alias->issue;
        }

        return $map;
    }

    /**
     * Returns true if $date is a valid date in the format "d.m.Y".
     *
     * @param $date
     * @param $format
     * @return bool
     */
    public function validateDate($date, $format = 'd.m.Y')
    {
        $d = DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

}
