<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class BookOptedOutOfTtl extends Book
{
    protected $table = "books";
    protected $cacheTtlSeconds = 0;
}
