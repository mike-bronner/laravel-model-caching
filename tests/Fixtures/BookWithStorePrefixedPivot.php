<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookWithStorePrefixedPivot extends Book
{
    protected $table = 'books';

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'book_store', 'book_id', 'store_id')
            ->using(StorePrefixedBookStorePivot::class);
    }
}
