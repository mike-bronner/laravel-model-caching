<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithRuntimeCacheStateAuthor extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        return $this->belongsTo(AuthorWithRuntimeCacheState::class, "author_id");
    }
}
