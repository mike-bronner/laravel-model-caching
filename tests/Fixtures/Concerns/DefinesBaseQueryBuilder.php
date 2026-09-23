<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorBaseQueryBuilder;

/**
 * Supplies a custom base query builder from a trait, the way
 * staudenmeir/laravel-cte's QueriesExpressions does. A model using this beside
 * Cachable must compile: if the package also declared newBaseQueryBuilder() in
 * a trait, PHP would refuse to declare the class over the collision.
 */
trait DefinesBaseQueryBuilder
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
