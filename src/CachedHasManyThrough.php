<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\CachesOneOrManyThrough;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CachedHasManyThrough extends HasManyThrough
{
    use Caching;
    use CachesOneOrManyThrough {
        CachesOneOrManyThrough::makeCacheKey insteadof Caching;
        CachesOneOrManyThrough::makeCacheTags insteadof Caching;
    }

    protected function makeCacheTags(): array
    {
        $eagerLoad = $this->eagerLoad ?? [];
        $model = $this->getModel();
        $query = $this->getQuery()->getQuery();

        $tags = (new CacheTags($eagerLoad, $model, $query))->make();

        // Include the intermediate (through) model's tag so that
        // flushing the through model also invalidates this cache.
        $throughTags = (new CacheTags([], $this->throughParent, $query))->make();

        return collect($tags)
            ->merge($throughTags)
            ->unique()
            ->values()
            ->all();
    }
}
