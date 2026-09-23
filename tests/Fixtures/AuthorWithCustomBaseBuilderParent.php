<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Supplies a custom base query builder from a class, so that a subclass mixing
 * in Cachable hands this builder to the package's newEloquentBuilder(), which
 * must keep it rather than swap in its own.
 */
abstract class AuthorWithCustomBaseBuilderParent extends Model
{
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new AuthorBaseQueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor(),
        );
    }
}
