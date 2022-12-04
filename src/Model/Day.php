<?php

namespace App\Model;

class Day extends Node
{
    public function __construct(public string $date) { }

    public function __toString(): string
    {
        return "{$this->date} +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n"
            .parent::__toString();
    }
}
