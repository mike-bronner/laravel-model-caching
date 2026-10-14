<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithDefaultEagerLoads extends AuthorWithTtlRelations
{
    protected $table = "authors";
    protected $with = ["booksWithTtl"];
}
