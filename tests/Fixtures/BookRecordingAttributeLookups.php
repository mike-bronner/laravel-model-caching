<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class BookRecordingAttributeLookups extends Book
{
    public static array $lookups = [];
    public static int $afterQueryCallbackReads = 0;

    protected $table = "books";

    public function __isset($key)
    {
        static::$lookups[] = $key;

        return parent::__isset($key);
    }

    public function __get($key): mixed
    {
        static::$lookups[] = $key;

        return parent::__get($key);
    }

    public function getAttribute($key)
    {
        static::$lookups[] = $key;

        return parent::getAttribute($key);
    }

    public function isRelation($key)
    {
        static::$lookups[] = $key;

        return parent::isRelation($key);
    }

    protected function afterQueryCallbacksOf(mixed $builder): array
    {
        static::$afterQueryCallbackReads++;

        return parent::afterQueryCallbacksOf($builder);
    }
}
