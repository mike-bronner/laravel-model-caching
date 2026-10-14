<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithModelRelation extends Book
{
    protected $table = "books";

    public function model(): BelongsTo
    {
        return $this->belongsTo(Author::class, "author_id");
    }
}
