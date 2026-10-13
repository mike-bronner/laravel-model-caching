<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Database\Eloquent\Builder;

class ModelCaching
{
    protected static $builder = Builder::class;

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
