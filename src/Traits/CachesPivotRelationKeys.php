<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Exceptions\UnkeyableValueException;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait CachesPivotRelationKeys
{
    protected string $defaultPivotAccessor = "pivot";

    protected function cacheKeyQuery(): QueryBuilder
    {
        return $this->query
            ->applyScopes()
            ->getQuery();
    }

    protected function makeCacheKey(
        array $columns = ['*'],
        $idColumn = null,
        string $keyDifferentiator = '',
    ): string {
        try {
            $eagerLoadsKeySegment = $this->eagerLoadsKeySegment();
        } catch (UnkeyableValueException) {
            $this->disableModelCaching();

            return "";
        }

        return $this->makeRelationCacheKey(
            $columns,
            $idColumn,
            $keyDifferentiator
                . $this->pivotColumnsKeySegment()
                . $this->pivotAccessorKeySegment()
                . $this->pivotClassKeySegment()
                . $eagerLoadsKeySegment,
        );
    }

    protected function eagerLoadsKeySegment(): string
    {
        $eagerLoads = $this->cacheEagerLoads();

        if (! $eagerLoads) {
            return "";
        }

        $related = $this->getRelated();
        $eagerLoadsKey = new CacheKey(
            $eagerLoads,
            $related,
            $related->newQueryWithoutRelationships()->getQuery(),
            "",
            [],
            false,
        );

        return "-with_" . sha1($eagerLoadsKey->make());
    }

    protected function pivotColumnsKeySegment(): string
    {
        if (! $this->pivotColumns) {
            return "";
        }

        return "-pivot_" . implode(",", $this->pivotColumns);
    }

    protected function pivotAccessorKeySegment(): string
    {
        if ($this->accessor === $this->defaultPivotAccessor) {
            return "";
        }

        return "-as_{$this->accessor}";
    }

    protected function pivotClassKeySegment(): string
    {
        if ($this->using === null) {
            return "";
        }

        return "-using_{$this->using}";
    }
}
