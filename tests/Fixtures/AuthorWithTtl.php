<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithTtl extends Author
{
    protected $table = "authors";
    protected $cacheTtlSeconds = 1;
}
