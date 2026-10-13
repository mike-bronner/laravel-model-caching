<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

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
