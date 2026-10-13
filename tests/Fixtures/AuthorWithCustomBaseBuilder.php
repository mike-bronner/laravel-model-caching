<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

class AuthorWithCustomBaseBuilder extends AuthorWithCustomBaseBuilderParent
{
    use Cachable;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
