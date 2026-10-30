<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithSwitchingRelation extends Book
{
    protected $table = "books";

    public function switching() : BelongsTo
    {
        return $this->getAttribute("switch_to_publisher")
            ? $this->belongsTo(Publisher::class, "publisher_id")
            : $this->belongsTo(Author::class, "author_id");
    }
}
