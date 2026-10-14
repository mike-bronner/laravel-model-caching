<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookOnTenantConnection extends Book
{
    protected $connection = 'tenant';
    protected $table = 'books';

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'book_store', 'book_id', 'store_id')
            ->using(CachableBookStorePivot::class);
    }
}
