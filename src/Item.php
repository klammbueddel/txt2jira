<?php

namespace App;

use DateInterval;
use DateTime;

class Item
{

    private $done = false;
    private $standalone = false;

    public function __construct(
        private ?string $issue,
        private array $comments,
        private string $start,
        private string $end,
        private array $children = [],
    ) {
        foreach ($this->comments as $idx => $comment) {
            if (preg_match('/(.*) x[\\s]*$/', $comment, $matches)) {
                $this->comments[$idx] = trim($matches[1]);
                $this->done = true;
            } else {
                $this->comments[$idx] = trim($this->comments[$idx]);
            }
        }
    }

    /**
     * @return string
     */
    public function getIssue(): ?string
    {
        if (preg_match('/^([A-Z]{2,}-[0-9]+) (.*)/', $this->issue ?? '', $matches)) {
            return $matches[1];
        }

        return $this->issue;
    }

    /**
     * @return string
     */
    public function getAlias(): ?string
    {
        if (preg_match('/^([A-Z]{2,}-[0-9]+) \( (.*) \)/', $this->issue ?? '', $matches)) {
            return $matches[2];
        }

        return $this->issue;
    }

    public function markDone()
    {
        $this->done = true;
        return $this;
    }

    public function markStandalone()
    {
        $this->standalone = true;
        return $this;
    }

    /**
     * @return string
     */
    public function getStart(): string
    {
        return $this->start;
    }

    /**
     * @return string
     */
    public function getEnd(): string
    {
        return $this->end;
    }

    /**
     * @return bool
     */
    public function isDone(): bool
    {
        return $this->done;
    }

    /**
     * @return bool
     */
    public function isStandalone(): bool
    {
        return $this->standalone;
    }

    /**
     * @return string
     */
    public function getComment(): ?string
    {
        return Exporter::sanitizeComments($this->comments);
    }

    public static function getEndTime($start, $minutes): string
    {
        $date = new DateTime($start);
        $date->add(new DateInterval('PT' . $minutes . 'M'));
        return $date->format('H:i');
    }

    public function getMinutes(): int
    {
        return round(
                ((new DateTime($this->end))->getTimestamp()
                    - (new DateTime($this->start))->getTimestamp()) / 60
            ) - $this->getMinutesOfChildren();
    }

    public function addChild(Item $item)
    {
        $this->children[] = $item;
        return $this;
    }

    public function getMinutesOfChildren()
    {
        $result = 0;
        foreach ($this->children as $child) {
            $result += $child->getMinutes();
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
