<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class CommentableType
{
    public function __construct(public string $key)
    {
    }

    public function __toString(): string
    {
        return "commentable type {$this->key}";
    }
}
