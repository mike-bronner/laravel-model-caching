<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BookWithFixableRelation extends Book
{
    public static bool $broken = true;

    protected $table = "books";

    public function fixable() : BelongsTo
    {
        if (static::$broken) {
            throw new RuntimeException("The fixable relation is broken.");
        }

        return $this->belongsTo(Publisher::class, "publisher_id");
    }
}
