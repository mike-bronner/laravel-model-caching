<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class TenantAuthorWithTtl extends AuthorWithTtl
{
    protected $connection = "tenant";
    protected $table = "authors";
}
