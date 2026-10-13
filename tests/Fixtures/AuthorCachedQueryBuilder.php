<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\CachedBuilder;

class AuthorCachedQueryBuilder extends CachedBuilder
{
    public function famous(): static
    {
        return $this->where('is_famous', true);
    }
}
