<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A book whose author carries its own `$cachePrefix`. PrefixedAuthor reads the
// same `authors` table as Author, so the two differ in exactly one thing: the
// prefix their tags are written under. That isolates the cross-prefix case —
// any tag difference between this model's queries and Book's is the prefix.
class BookWithPrefixedAuthor extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        return $this->belongsTo(PrefixedAuthor::class, "author_id");
    }
}
