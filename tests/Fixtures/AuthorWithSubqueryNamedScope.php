<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class AuthorWithSubqueryNamedScope extends AuthorWithCustomBaseAndEloquentBuilder
{
    protected $table = "authors";

    public function scopeWhereNotIn(Builder $query, string $name): Builder
    {
        return $query->where("name", $name);
    }
}
