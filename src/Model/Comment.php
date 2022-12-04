<?php

namespace App\Model;


class Comment
{
    public function __construct(
        public Issue $issue,
        public string $ticket,
        public string $alias,
        public string $comment,
    ) {
    }
}
