<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Query\Builder;

/**
 * A custom *base* query builder — the Illuminate\Database\Query\Builder layer,
 * not the Eloquent one the other builder fixtures cover. It exists to prove
 * that the package leaves a consumer's own base builder alone rather than
 * replacing it with CachedQueryBuilder.
 */
class AuthorBaseQueryBuilder extends Builder
{
}
