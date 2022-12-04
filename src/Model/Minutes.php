<?php

namespace App\Model;

class Minutes extends Node
{
    public function __construct(public string $minutes) { }

    public function __toString(): string
    {
        return trim("{$this->minutes}m ".parent::__toString());
    }
}
