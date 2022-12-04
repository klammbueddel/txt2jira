<?php

namespace App\Model;

class EmptyLine extends Node
{
    public function __construct() { }

    public function __toString(): string
    {
        return "\n";
    }
}
