<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

trait FakeNodeTrait
{
    public function newEloquentBuilder($query)
    {
        return parent::newEloquentBuilder($query);
    }
}
