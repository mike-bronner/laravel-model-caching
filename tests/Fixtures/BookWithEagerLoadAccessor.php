<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class BookWithEagerLoadAccessor extends Book
{
    protected $table = "books";

    public function getEagerLoadAttribute(): string
    {
        return "accessor value";
    }
}
