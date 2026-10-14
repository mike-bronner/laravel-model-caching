<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BookWithUnloadableModelRelation extends Book
{
    protected $table = "books";

    public function model(): BelongsTo
    {
        throw new RuntimeException("The model() relationship was resolved during a write.");
    }
}
