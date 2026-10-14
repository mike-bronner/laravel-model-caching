<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorWithEmptyPrefixAndCooldown extends Author
{
    protected $cacheCooldownSeconds = 60;
    protected $cachePrefix = "";
    protected $table = "authors";
}
