<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
