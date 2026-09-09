<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

/**
 * A book with a method that looks like a relation and is not one.
 *
 * Laravel resolves a relation name by calling the method and using whatever
 * comes back, so a method returning something other than a Relation reaches the
 * tag recording before Laravel rejects it. The recording has to hand that value
 * straight back and let Laravel raise its own error.
 */
class BookWithFakeRelation extends Book
{
    protected $table = "books";

    public function notReallyARelation()
    {
        return null;
    }
}
