<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\CacheTags;

/**
 * A tag builder that renames the connection its own model keys under.
 *
 * getConnectionName() is protected on the CachePrefixing trait, so overriding
 * it is how a consumer customises the connection segment of every prefix this
 * object writes. The override only reaches the tags while the querying model is
 * routed back through the method; a prefix builder that reads the connection
 * off the model directly leaves the override declared and never called.
 */
class CacheTagsWithOverriddenConnectionName extends CacheTags
{
    protected function getConnectionName() : string
    {
        return "overridden-connection";
    }
}
