<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StoreWithBooks extends Store
{
    protected $table = "stores";
    protected $with = [
        "books",
    ];

    public function books() : BelongsToMany
    {
        return $this->belongsToMany(Book::class, "book_store", "store_id", "book_id");
    }
}
