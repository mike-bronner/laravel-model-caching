<?php

use Illuminate\Database\Eloquent\Builder;

function withGlobalEloquentMacro(string $name, Closure $macro, Closure $callback): mixed
{
    $macros = Closure::bind(fn () => static::$macros, null, Builder::class);
    $restore = Closure::bind(function (array $saved): void {
        static::$macros = $saved;
    }, null, Builder::class);
    $saved = $macros();
    Builder::macro($name, $macro);

    try {
        return $callback();
    } finally {
        $restore($saved);
    }
}
