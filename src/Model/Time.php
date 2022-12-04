<?php

namespace App\Model;

class Time extends Node
{

    public function __construct(public string $time) { }

    public function __toString(): string
    {
        return trim("$this->time ".parent::__toString())."\n";
    }
}
