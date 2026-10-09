<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void invalidate(string|array $modelClasses)
 * @method static mixed runDisabled(callable $closure)
 *
 * @see \GeneaLabs\LaravelModelCaching\Helper
 */
class ModelCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'model-cache';
    }
}
