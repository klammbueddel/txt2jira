<?php

namespace App\Model;

class Issue extends Node
{

    public function __construct(
        public string $input,
        public bool $isDone,
        public ?string $issue = null,
        public ?string $alias = null,
        public ?string $comment = null,
        public ?float $weight = null,
    ) {
    }

    public function __toString(): string
    {
        return trim("$this->input ".($this->isDone ? 'x' : ''))."\n";
    }
}
