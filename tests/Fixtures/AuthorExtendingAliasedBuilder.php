<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

class AuthorExtendingAliasedBuilder extends AuthorWithAliasedBuilder
{
    use Cachable;
}
