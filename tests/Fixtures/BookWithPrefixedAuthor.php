<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithPrefixedAuthor extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        return $this->belongsTo(PrefixedAuthor::class, "author_id");
    }
}
