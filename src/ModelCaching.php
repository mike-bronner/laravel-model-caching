<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Database\Eloquent\Builder;

class ModelCaching
{
    protected static $builder = Builder::class;

    /**
     * @deprecated Since custom builder support was added in v12.x. Use the
     *             standard Laravel approach instead: define a custom builder
     *             class and return it from your model's newEloquentBuilder().
     *             CachedBuilder now extends Illuminate\Database\Eloquent\Builder
     *             directly and will compose your custom builder automatically.
     *
     * @see \GeneaLabs\LaravelModelCaching\Traits\ModelCaching::newModelCachingEloquentBuilder()
     */
    public static function useEloquentBuilder(string $builder) : void
    {
        trigger_error(
            'ModelCaching::useEloquentBuilder() is deprecated. '
            . 'Use a custom builder via newEloquentBuilder() on your model instead.',
            E_USER_DEPRECATED,
        );

        self::$builder = $builder;
    }

    public static function builder() : string
    {
        return self::$builder;
    }
}
