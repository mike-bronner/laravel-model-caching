<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use WeakMap;

class RelationResolver
{
    protected static array $classRelationPaths = [];

    protected WeakMap $resolved;

    public function __construct()
    {
        $this->resolved = new WeakMap;
    }

    public static function forgetClassRelationPaths(): void
    {
        static::$classRelationPaths = [];
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

    // Answers an eager-load path from what is remembered for the model class, or
    // returns null when the path has to be resolved live: a MorphTo, a relation
    // registered at runtime, or a name that is not a relation method. Only class
    // facts are remembered. Connections, prefixes and TTLs are read from the
    // models this builds on every call.
    public function relationPath(Model $model, string $path): ?array
    {
        $segments = static::$classRelationPaths[$model::class][$path]
            ??= $this->classRelationPath($model, $path);

        if (! $segments) {
            return null;
        }

        $parent = $model;

        return array_map(function (array $segment) use (&$parent): array {
            $related = $this->relatedModel($parent, $segment["related"]);
            $pivot = $segment["pivot"]
                ? $segment["pivot"]::fromAttributes($parent, [], $segment["table"])
                : null;
            $resolved = [
                "parent" => $parent,
                "related" => $related,
                "pivot" => $pivot,
                "table" => $segment["table"],
            ];
            $parent = $related;

            return $resolved;
        }, $segments);
    }

    // Resolves each segment on a fresh instance with no constraints, the way
    // Laravel's eager loader does, so the attributes of a hydrated model never
    // pick the relation. False means "resolve this path live".
    protected function classRelationPath(Model $model, string $path): array|false
    {
        $segments = [];
        $owner = $model;

        foreach (explode(".", $path) as $name) {
            if (! method_exists($owner, $name)) {
                return false;
            }

            $relation = Relation::noConstraints(fn () => $owner->newInstance()->{$name}());

            if (
                ! $relation instanceof Relation
                || $relation instanceof MorphTo
            ) {
                return false;
            }

            $owner = $relation->getRelated();
            $isPivoted = $relation instanceof BelongsToMany;
            $segments[] = [
                "related" => $owner::class,
                "pivot" => $isPivoted ? $relation->newPivot()::class : null,
                "table" => $isPivoted ? $relation->getTable() : null,
            ];
        }

        return $segments;
    }

    protected function relatedModel(Model $parent, string $class): Model
    {
        $related = new $class;

        if (! $related->getConnectionName()) {
            $related->setConnection($parent->getConnectionName());
        }

        return $related;
    }
}
