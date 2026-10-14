<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorBaseQueryBuilder;

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
