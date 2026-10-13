<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class BookWithFakeRelation extends Book
{
    protected $table = "books";

    public function notReallyARelation()
    {
        return null;
    }
}
