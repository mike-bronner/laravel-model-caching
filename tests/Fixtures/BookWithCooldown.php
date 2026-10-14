<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookWithCooldown extends Book
{
    protected $table = "books";
    protected $cacheCooldownSeconds = 60;

    public function stores() : BelongsToMany
    {
        return $this->belongsToMany(Store::class, "book_store", "book_id", "store_id");
    }

    public function prefixedStores(): BelongsToMany
    {
        return $this->belongsToMany(
                PrefixedStoreWithCooldown::class,
                "book_store",
                "book_id",
                "store_id",
            );
    }
}
