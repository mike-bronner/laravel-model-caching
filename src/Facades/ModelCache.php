<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Facades;

use Illuminate\Support\Facades\Facade;

class ModelCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'model-cache';
    }
}
