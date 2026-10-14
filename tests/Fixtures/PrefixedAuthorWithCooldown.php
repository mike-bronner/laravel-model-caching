<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PrefixedAuthorWithCooldown extends PrefixedAuthor
{
    protected $table = "authors";
    protected $cacheCooldownSeconds = 60;
}
