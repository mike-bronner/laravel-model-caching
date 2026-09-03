<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

/**
 * A cachable Author whose base query builder comes from the class the Cachable
 * trait is mixed in over.
 *
 * The intermediate parent matters. A trait method beats a method inherited from
 * a parent class, so the package's newBaseQueryBuilder() is the one that runs
 * here — which is exactly the case where it could clobber a consumer's builder
 * without ever being asked to. (A model declaring newBaseQueryBuilder() in its
 * own class body is safe by PHP's own precedence rules: the class body beats
 * the trait, and the package's version never runs at all.)
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
