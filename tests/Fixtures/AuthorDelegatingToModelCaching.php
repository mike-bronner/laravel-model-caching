<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class AuthorDelegatingToModelCaching extends Author
{
    protected $table = "authors";

    public function newEloquentBuilder($query): Builder
    {
        return $this->newModelCachingEloquentBuilder($query);
    }
}
