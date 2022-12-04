<?php

namespace App\Model;

class Pause extends Node
{
    public function __construct(public bool $isDone) { }

    public function __toString(): string
    {
        return ($this->isDone ? 'x' : '')."\n";
    }
}
