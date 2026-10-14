<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PrefixedBook extends Book
{
    protected $cachePrefix = "book-prefix";
    protected $table = "books";
}
