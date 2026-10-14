<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithPublisherWithTtl extends Book
{
    protected $table = "books";

    public function publisher() : BelongsTo
    {
        return $this->belongsTo(PublisherWithTtl::class, "publisher_id");
    }
}
