<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithClassBodyBuilder extends Author
{
    protected $table = "authors";

    public function newEloquentBuilder($query): AuthorCachedQueryBuilder
    {
        return new AuthorCachedQueryBuilder($query);
    }
}
