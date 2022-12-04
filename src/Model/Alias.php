<?php

namespace App\Model;

class Alias extends Node
{
    public function __construct(
        public $issue,
        public $alias,
    )
    {
    }

    public function __toString(): string
    {
        return $this->issue . ' as ' . $this->alias . "\n";
    }

}
