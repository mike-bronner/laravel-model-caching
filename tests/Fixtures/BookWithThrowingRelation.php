<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BookWithThrowingRelation extends Book
{
    public static int $calls = 0;

    protected $table = "books";

    public function throwing() : BelongsTo
    {
        static::$calls++;

        throw new RuntimeException("The throwing relation failed.");
    }
}
