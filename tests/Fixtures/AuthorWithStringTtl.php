<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithStringTtl extends Author
{
    protected $table = "authors";
    protected $cacheTtlSeconds = "30";
}
