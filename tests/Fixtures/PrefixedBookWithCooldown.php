<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PrefixedBookWithCooldown extends PrefixedBook
{
    protected $cacheCooldownSeconds = 60;
    protected $table = "books";
}
