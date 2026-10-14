<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithTtl extends AuthorWithTtlRelations
{
    protected $table = "authors";
    protected $cacheTtlSeconds = 30;
}
