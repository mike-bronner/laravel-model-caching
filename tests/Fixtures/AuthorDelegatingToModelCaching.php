<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

/**
 * An Author that resolves the #535 newEloquentBuilder() collision the other
 * way round: it keeps the other trait's method and delegates to
 * newModelCachingEloquentBuilder() itself, so the package's own
 * newEloquentBuilder() never runs.
 */
class AuthorDelegatingToModelCaching extends Model
{
    use Cachable, FakeNodeTrait {
        FakeNodeTrait::newEloquentBuilder insteadof Cachable;
    }

    protected $table = 'authors';
    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];

    public function newEloquentBuilder($query)
    {
        return $this->newModelCachingEloquentBuilder($query);
    }
}
