<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cachable Author that keeps its own base query builder and also has a
 * relation to traverse.
 *
 * Recording the tables of a compiled-away subquery needs the recording base
 * builder. This model does not have one — the package left the consumer's
 * builder in place — so anything that would record has to find that out and
 * do nothing, rather than call a method the builder does not have.
 */
class AuthorWithCustomBaseBuilderAndBooks extends AuthorWithCustomBaseBuilder
{
    public function books() : HasMany
    {
        return $this->hasMany(Book::class, "author_id");
    }
}
