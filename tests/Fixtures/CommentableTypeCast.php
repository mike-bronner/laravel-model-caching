<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CommentableTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CommentableType
    {
        return $value === null
            ? null
            : new CommentableType($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof CommentableType
            ? $value->key
            : $value;
    }
}
