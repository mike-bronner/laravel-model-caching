<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PublisherWithTtl extends Publisher
{
    protected $table = "publishers";
    protected $cacheTtlSeconds = 20;
}
