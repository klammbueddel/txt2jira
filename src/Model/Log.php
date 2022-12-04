<?php

namespace App\Model;

use DateInterval;
use DateTime;

class Log
{
    /**
     * @param DateTime $start
     * @param Issue[] $issues
     * @param DateTime|null $end
     * @param Log[] $children
     * @param bool $transient
     */
    public function __construct(
        public DateTime $start,
        public array $issues = [],
        public ?DateTime $end = null,
        public array $children = [],
        public $transient = false,
    ) {
    }

    public function addChild(Log $item)
    {
        $this->children[] = $item;

        return $this;
    }

    public function getMinutes(): int
    {
        $end = $this->end ?: (clone $this->start)->add(new DateInterval('P1D'))->setTime(0,0);
        return round(($end->getTimestamp() - $this->start->getTimestamp()) / 60) - $this->getMinutesOfChildren();
    }

    public function getMinutesOfChildren()
    {
        $result = 0;
        foreach ($this->children as $child) {
            $result += $child->getMinutes();
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->start->format('H:i') . ' - ' . ($this->end ? $this->end->format('H:i') : '--:--') . ' ' . implode(' ', $this->issues) . "\n";
    }
}
