<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class AuthorQueryBuilder extends Builder
{
    public function famous(): static
    {
        return $this->where('is_famous', true);
    }
}
