<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorOptedOutOfTtl extends AuthorWithTtlRelations
{
    protected $table = "authors";
    protected $cacheTtlSeconds = 0;
}
