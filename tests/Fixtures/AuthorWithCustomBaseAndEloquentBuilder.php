<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithCustomBaseAndEloquentBuilder extends AuthorWithCustomBaseBuilder
{
    protected static string $builder = AuthorSubqueryEloquentBuilder::class;

    protected $table = "authors";
}
