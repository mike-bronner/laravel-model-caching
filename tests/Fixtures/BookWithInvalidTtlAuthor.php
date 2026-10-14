<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithInvalidTtlAuthor extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        return $this->belongsTo(AuthorWithInvalidTtl::class, "author_id");
    }
}
