<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Supplies a custom base query builder from a class, so that a subclass mixing
 * in Cachable reaches the package's newBaseQueryBuilder() rather than this one
 * — a trait method beats a method inherited from a parent class.
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
