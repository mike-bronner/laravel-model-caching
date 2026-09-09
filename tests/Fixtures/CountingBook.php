<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A book that counts how many times its relation method is called. Reads the
// same `books` table as Book and returns the same relation, so the only thing
// that distinguishes it is the tally — which is what pins how often the
// tag-recording code resolves a relation.
class CountingBook extends Book
{
    protected $table = "books";

    public static int $authorCalls = 0;

    public function author() : BelongsTo
    {
        static::$authorCalls++;

        return $this->belongsTo(Author::class, "author_id");
    }
}
