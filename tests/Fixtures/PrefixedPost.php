<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PrefixedPost extends Post
{
    protected $cachePrefix = "post-prefix";
    protected $table = "posts";
}
