<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\CacheTags;

class CacheTagsWithOverriddenConnectionName extends CacheTags
{
    protected function getConnectionName() : string
    {
        return "overridden-connection";
    }
}
