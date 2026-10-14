<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithMacroEloquentBuilder extends AuthorWithCustomBaseBuilder
{
    protected static string $builder = AuthorMacroEloquentBuilder::class;

    protected $table = "authors";
}
