<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithFilteringBuilder extends AuthorWithCustomBuilder
{
    protected static string $builder = AuthorFilteringQueryBuilder::class;
}
