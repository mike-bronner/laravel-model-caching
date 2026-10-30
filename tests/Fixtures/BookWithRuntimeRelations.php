<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithRuntimeRelations extends Book
{
    protected $table = "books";

    public function author() : BelongsTo
    {
        return $this->belongsTo(RuntimeRelationAuthor::class, "author_id");
    }
}
