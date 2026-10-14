<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class BookWithTtl extends Book
{
    protected $table = "books";
    protected $cacheTtlSeconds = 30;
}
