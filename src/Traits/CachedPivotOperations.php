<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelPivotEvents\Traits\FiresPivotEventsTrait;

trait CachedPivotOperations
{
    use FiresPivotEventsTrait {
        FiresPivotEventsTrait::sync as traitSync;
        FiresPivotEventsTrait::attach as traitAttach;
        FiresPivotEventsTrait::detach as traitDetach;
        FiresPivotEventsTrait::updateExistingPivot as traitUpdateExistingPivot;
    }

    protected $isSyncing = false;

    protected function flushCacheForPivotOperation(): void
    {
        collect([$this->parent, $this->getRelated()])
            ->filter(fn ($model) => method_exists($model, 'flushCache'))
            ->each(function ($model): void {
                $this->withCacheFallback(function () use ($model) {
                    if ($this->cacheCooldownAllowsFlush($model)) {
                        $model->flushCache();
                    }
                }, 'cache flush after pivot write failed');
            });
    }

    public function sync($ids, $detaching = true): array|false
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;
        $this->isSyncing = true;

        try {
            $result = $this->traitSync($ids, $detaching);
        } finally {
            $this->isSyncing = false;
            $this->isCachable = $wasCachable;
        }

        $this->flushCacheForPivotOperation();

        return $result;
    }

    public function attach($ids, array $attributes = [], $touch = true): void
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $this->traitAttach($ids, $attributes, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }
    }

    public function detach($ids = null, $touch = true): int
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $result = $this->traitDetach($ids, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }

        return $result;
    }

    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $result = $this->traitUpdateExistingPivot($id, $attributes, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }

        return $result;
    }
}
