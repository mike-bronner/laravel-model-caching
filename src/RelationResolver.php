<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

class RelationResolver
{
    protected WeakMap $resolved;

    public function __construct()
    {
        $this->resolved = new WeakMap;
    }

    public function resolve(Model $model, string $relationName): mixed
    {
        $relations = $this->resolved[$model] ?? [];

        if (! array_key_exists($relationName, $relations)) {
            $relations[$relationName] = $model->{$relationName}();
            $this->resolved[$model] = $relations;
        }

        return $relations[$relationName];
    }
}
