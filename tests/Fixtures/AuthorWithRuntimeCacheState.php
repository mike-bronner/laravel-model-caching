<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithRuntimeCacheState extends Author
{
    public static string $runtimeCachePrefix = "first";
    public static ?int $runtimeTtl = null;

    protected $cachePrefix;
    protected $cacheTtlSeconds;
    protected $table = "authors";

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->cachePrefix = static::$runtimeCachePrefix;
        $this->cacheTtlSeconds = static::$runtimeTtl;
    }
}
