<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use SplObjectStorage;

class RelationCallLog
{
    public static ?SplObjectStorage $calls = null;

    public static function record(object $model, string $relation): void
    {
        static::$calls ??= new SplObjectStorage;
        $counts = static::$calls->contains($model)
            ? static::$calls[$model]
            : [];
        $counts[$relation] = ($counts[$relation] ?? 0) + 1;
        static::$calls[$model] = $counts;
    }

    public static function reset(): void
    {
        static::$calls = new SplObjectStorage;
    }
}
