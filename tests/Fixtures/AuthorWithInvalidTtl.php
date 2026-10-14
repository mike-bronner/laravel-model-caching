<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithInvalidTtl extends Author
{
    protected $table = "authors";
    protected $cacheTtlSeconds = "60s";
}
