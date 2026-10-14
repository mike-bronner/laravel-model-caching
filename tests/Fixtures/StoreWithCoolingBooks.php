<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StoreWithCoolingBooks extends Store
{
    protected $table = "stores";

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(
                PrefixedBookWithCooldown::class,
                "book_store",
                "store_id",
                "book_id",
            );
    }
}
