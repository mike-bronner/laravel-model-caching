<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

/**
 * A cachable Author whose base query builder comes from the class the Cachable
 * trait is mixed in over.
 *
 * The package swaps in its recording query builder from newEloquentBuilder(),
 * after the model's own newBaseQueryBuilder() has run, so it sees the builder
 * this parent supplies and has to decide whether to replace it. It must leave
 * it alone: only Laravel's own base query builder is ever swapped.
 */
class AuthorWithCustomBaseBuilder extends AuthorWithCustomBaseBuilderParent
{
    use Cachable;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
