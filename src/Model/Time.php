<?php

namespace App\Model;

class Time extends Node
{

    public function __construct(public string $time) { }

    public function __toString(): string
    {
        if ($this->children && $this->children[0] instanceof Minutes) {
            return trim("$this->time ".parent::__toString())."\n";
        } else {
            return trim("$this->time\n".parent::__toString())."\n";
        }
    }
}
