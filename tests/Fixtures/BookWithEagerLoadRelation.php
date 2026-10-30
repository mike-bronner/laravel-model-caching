<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithEagerLoadRelation extends Book
{
    protected $table = "books";

    public function eagerLoad() : BelongsTo
    {
        return $this->belongsTo(Author::class, "author_id");
    }
}
