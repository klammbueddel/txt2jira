<?php

namespace App;

use Exception;
use Jfcherng\Diff\DiffHelper;

class Importer
{

    private array $aliases = [];

    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * @param $file
     * @return array
     * @throws Exception
     */
    function import($file): array
    {
        return $this->parse(file_get_contents($file));
    }

    function export($file, $days): void
    {
        file_put_contents($file, $this->render($days));
    }

    private function collectAlias($line): bool
    {
        if (preg_match('/^([A-Z]{2,}-[0-9]+)(.*)/', $line, $matches)) {
            $issue = $matches[1];

            $possibleAlias = $matches[2];
            if (preg_match('/^ as ([_\w]+)/', $possibleAlias, $matches)) {
                $this->aliases[$matches[1]] = $issue;

                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    function parse($content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        $result = [];

        $date = null;
        $time = null;
        $lastTime = null;
        $issue = null;
        $comments = [];
        $collectAliases = true;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($collectAliases && $this->collectAlias($line)) {
                continue;
            }

            if (preg_match('/^([0-9]{2}\.[0-9]{2}\.[0-9]{4}).*/', $line, $matches)) {
                $date = $matches[1];
                $lastTime = null;
                $time = null;
                $comments = [];
                continue;
            }

            $resolvedAlias = false;
            foreach ($this->aliases as $alias => $resolveTo) {
                if (str_starts_with($line, $alias)) {
                    $issue = $resolveTo." ( $alias )";
                    $comments = [substr($line, strlen($alias))];
                    $resolvedAlias = true;
                    break;
                }
            }
            if ($resolvedAlias) {
                continue;
            }

            # matches issue
            if (preg_match('/^([A-Z]{2,}-[0-9]+)(.*)/', $line, $matches)) {
                $issue = $matches[1];
                $comments = [$matches[2]];
                continue;
            }

            # matches empty line (pause)
            if (!trim($line)) {
                $time = null;
                if ($lastTime) {
                    $comments[] = '';
                }
                continue;
            }

            # matches time
            if (preg_match('/^([0-9]{2}:[0-9]{2}).*/', $line, $matches)) {
                $collectAliases = false;
                $time = $matches[1];
                if ($lastTime) {
                    $result[$date][] = new Item($issue, $comments, $lastTime, $time);
                    $comments = [];
                    $issue = null;
                }
            } else {
                $comments[] = $line;
                $issue = null;
            }

            if ($time) {
                $lastTime = $time;
            }

        }

        return $result;
    }

    /**
     * @param $in
     * @return string
     * @throws Exception
     */
    function diff($in): string
    {
        $items = $this->parse($in);
        $out = $this->render($items);

        return DiffHelper::calculate($in, $out, 'Unified', ['ignoreWhitespace' => true]);
    }

    function render($logs): string
    {

        $lines = [];

        if ($this->aliases) {
            foreach ($this->aliases as $alias => $issue) {
                $lines[] = $issue.' as '.$alias;
            }
            $lines[] = '';
        }

        foreach ($logs as $date => $items) {
            $lines[] = $date.' +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++';
            $lines[] = '';
            $lastEnd = null;
            foreach ($items as $idx => $item) {
                if ($idx === 0) {
                    $lines[] = $item->getStart();
                } else {
                    if ($item->getStart() !== $lastEnd) {
                        $lines[] = '';
                        $lines[] = $item->getStart();
                    }
                }
                if ($item->getComment() !== null || $item->getAlias()) {
                    $lines[] = ($item->getAlias() ?: '')
                        .($item->getComment() ? ' '.$item->getComment() : '')
                        .($item->isDone() ? ' x' : '');
                }
                $lines[] = $item->getEnd();

                $lastEnd = $item->getEnd();
            }
            $lines[] = '';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

}
