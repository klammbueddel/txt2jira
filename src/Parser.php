<?php

namespace App;

use App\Model\Alias;
use App\Model\Day;
use App\Model\EmptyLine;
use App\Model\Issue;
use App\Model\Log;
use App\Model\Minutes;
use App\Model\Node;
use App\Model\Pause;
use App\Model\Time;
use Exception;
use Jfcherng\Diff\DiffHelper;
use function PHPUnit\Framework\stringEndsWith;

class Parser
{

    public function __construct(private readonly Config $config) { }

    private function parseAlias(string $line): ?Alias
    {
        if (preg_match('/^([A-Z]{2,}-[0-9]+) as ([_\w]+)/', $line, $matches)) {
            $issue = $matches[1];
            $alias = $matches[2];

            return new Alias($issue, $alias);
        }

        return null;
    }

    private function parseDay(string $line): ?Day
    {
        if (preg_match($this->config->dateRegex, $line, $matches)) {
            return new Day($matches[1]);
        }

        return null;
    }

    private function parseIssue(string $line): Issue|Pause
    {
        $line = trim($line);
        if ($line === '') {
            return new Pause(false);
        } elseif ($line === 'x') {
            return new Pause(true);
        } elseif (str_ends_with($line, ' x')) {
            return new Issue(substr($line, 0, strlen($line) - 2), true);
        } else {
            return new Issue($line, false);
        }

    }

    private function parseMinutes(string $line): ?Minutes
    {
        # matches time and pause
        if (preg_match('/^([0-9]+)m(.*)$/', $line, $matches)) {
            $minutes = new Minutes($matches[1]);
            $minutes->addChild($this->parseIssue(trim($matches[2])));

            return $minutes;
        }

        return null;
    }

    public function parseTime(string $line, $lineNumber = 0): ?Time
    {
        # matches time and pause
        if (preg_match('/^([0-9]{1,2}:[0-9]{2})(.*)$/', $line, $matches)) {
            $time = new Time($matches[1]);
            if ($minutes = $this->parseMinutes(trim($matches[2]))) {
                $time->addChild($minutes);
            } else if (trim($matches[2])) {
                throw new SyntaxError('Unexpected sequence ' . trim($matches[2]) . " at line $lineNumber");
            }
            return $time;
        }
        return null;
    }

    /**
     * @throws SyntaxError
     */
    function parse($content): Node
    {
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        $root = new Node();
        $day = null;

        foreach ($lines as $idx => $line) {
            $line = trim($line);

            if ($line === '') {
                ($day ?? $root)->addChild(new EmptyLine());
                continue;
            }

            if ($alias = $this->parseAlias($line)) {
                ($day ?? $root)->addChild($alias);
                continue;
            }

            if ($newDay = $this->parseDay($line)) {
                $day = $newDay;
                $root->addChild($day);
                continue;
            }

            if ($time = $this->parseTime($line, $idx)) {
                if (!$day) {
                    throw new SyntaxError("No day for time in line $idx");
                }
                $day->addChild($time);
                continue;
            }

            if (! $day) {
                throw new SyntaxError("No day at line $idx");
            }

            $day->addChild($this->parseIssue($line));

        }

        return $root;
    }

}
