<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrefixedStoreWithCooldown extends PrefixedStore
{
    protected $cacheCooldownSeconds = 60;
    protected $table = "stores";

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(PrefixedBook::class, "book_store", "store_id", "book_id");
    }
}
