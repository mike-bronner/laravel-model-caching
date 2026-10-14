<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class StorePrefixedBookStorePivot extends CachableBookStorePivot
{
    protected $cachePrefix = "store-prefix";
}
