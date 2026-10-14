<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\Buildable;
use GeneaLabs\LaravelModelCaching\Traits\BuilderCaching;
use GeneaLabs\LaravelModelCaching\Traits\CachedPivotOperations;
use GeneaLabs\LaravelModelCaching\Traits\CachesPivotRelationKeys;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CachedBelongsToMany extends BelongsToMany
{
    use Buildable;
    use BuilderCaching;
    use Caching, CachesPivotRelationKeys {
        CachesPivotRelationKeys::cacheKeyQuery insteadof Caching;
        CachesPivotRelationKeys::makeCacheKey insteadof Caching;
        Caching::makeCacheKey as makeRelationCacheKey;
    }
    use CachedPivotOperations;
}
