<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\CachedBuilder;

class AuthorWithTypedBuilder extends Author
{
    protected $table = "authors";

    public function newEloquentBuilder($query)
    {
        return parent::newEloquentBuilder($query);
    }

    public static function flushCacheNamed(string $name): void
    {
        static::where("name", $name)
            ->withCacheCooldownSeconds(0)
            ->flushCache();
    }
}
