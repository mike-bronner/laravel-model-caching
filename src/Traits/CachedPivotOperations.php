<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelPivotEvents\Traits\FiresPivotEventsTrait;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

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

        $this->flushPivotTableCache();
    }

    protected function flushPivotTableCache(): void
    {
        $isEnabled = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.enabled");

        if (! $isEnabled) {
            return;
        }

        $this->withCacheFallback(function (): void {
            $isCoolingDown = collect([$this->parent, $this->getRelated()])
                ->filter(fn ($model): bool => method_exists($model, "flushCache"))
                ->contains(fn (Model $model): bool => ! $this->cacheCooldownAllowsFlush($model));

            if ($isCoolingDown) {
                return;
            }

            $pivotTags = (new CacheTags([], $this->getRelated(), null))
                ->getPivotTableTags($this);

            $this->modelCacheRepository()
                ->invalidateTags($pivotTags);
        }, 'pivot table cache flush after pivot write failed');
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
