<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EagerLoadCountingBook extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        RelationCallLog::record($this, "author");

        return $this->belongsTo(EagerLoadCountingAuthor::class, "author_id");
    }

    public function publisher() : BelongsTo
    {
        RelationCallLog::record($this, "publisher");

        return parent::publisher();
    }

    public function stores() : BelongsToMany
    {
        RelationCallLog::record($this, "stores");

        return $this->belongsToMany(Store::class, "book_store", "book_id", "store_id");
    }
}
