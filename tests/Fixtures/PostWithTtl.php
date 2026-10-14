<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PostWithTtl extends Post
{
    protected $table = "posts";
    protected $cacheTtlSeconds = 30;
}
