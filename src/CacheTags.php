<?php namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\CachePrefixing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use SplObjectStorage;
use Throwable;

class CacheTags
{
    use CachePrefixing;

    protected $eagerLoad;
    protected $model;
    protected $query;

    public function __construct(
        array $eagerLoad,
        $model,
        $query
    ) {
        $this->eagerLoad = $eagerLoad;
        $this->model = $model;
        $this->query = $query;
    }

    public function make() : array
    {
        $tags = collect($this->eagerLoad)
            ->keys()
            ->flatMap(function ($relationName) {
                $morphToTags = $this->getMorphToTagsForRelation($relationName);

                if ($morphToTags !== null) {
                    return $morphToTags;
                }

                $relation = $this->getRelation($relationName);

                if (! $relation) {
                    return [];
                }

                return [$this->getCachePrefix()
                    . (new Str)->slug(get_class($relation->getQuery()->getModel()))];
            })
            ->filter()
            ->unique()
            ->prepend($this->getTagName())
            ->push($this->getTableTagName())
            ->values();

        $joinTags = $this->getJoinTags();
        $subqueryWhereTags = $this->getSubqueryWhereTags();

        return $tags->merge($joinTags)
            ->merge($subqueryWhereTags)
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getJoinTags() : array
    {
        $baseQuery = $this->query;

        if (method_exists($this->query, 'getQuery')) {
            $baseQuery = $this->query->getQuery();
        }

        $prefix = $this->getCachePrefix();

        return collect($baseQuery->joins ?? [])
            ->map(function ($join) {
                $table = $join->table;

                // `joinSub()` — and anything else joining a raw expression —
                // stores an Expression here instead of a table name, and the
                // stripos() below raises a TypeError on it. Eloquent's
                // `ofMany()` relations build exactly such a join, so any query
                // whose joins are already materialized when the tags are made
                // fails there. The table behind the expression is recovered
                // from the builder below, which recorded it before Laravel
                // compiled the subquery.
                if (! is_string($table)) {
                    return null;
                }

                // Strip alias (e.g. "products as p" -> "products")
                if (stripos($table, ' as ') !== false) {
                    $table = trim(explode(' as ', strtolower($table))[0]);
                }

                return $table;
            })
            ->merge($this->getJoinedSubqueryTables($baseQuery))
            ->filter(function ($table) {
                return $table !== null;
            })
            ->map(function ($table) use ($prefix) {
                return $prefix . (new Str)->slug($table);
            })
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getJoinedSubqueryTables(mixed $baseQuery) : array
    {
        if (! method_exists($baseQuery, "getJoinedSubqueryTables")) {
            return [];
        }

        return array_merge(
            $baseQuery->getJoinedSubqueryTables(),
            $this->getDeferredJoinedSubqueryTables($baseQuery),
        );
    }

    /**
     * Tables joined by a subquery that has not been built yet.
     *
     * Eloquent defers some of its own subquery joins into a beforeQuery()
     * callback — `ofMany()` is the one in Laravel — and tags are made before
     * the query runs, so those callbacks have not fired and there is nothing
     * recorded yet. Running them on a clone materializes the joins, and the
     * tables they read, without touching the query that will actually execute.
     *
     * The callbacks are shared by reference with the original query, so they
     * run once here and again at execution. That is the same trade CacheKey
     * already makes to build a key for an `ofMany()` query: harmless for
     * Eloquent's idempotent, self-clearing family, observable to a custom
     * non-idempotent callback. A callback that throws yields no tables rather
     * than breaking tag generation, which leaves the pre-existing gap in place
     * instead of failing the query outright.
     */
    protected function getDeferredJoinedSubqueryTables(mixed $baseQuery) : array
    {
        if (
            ! property_exists($baseQuery, "beforeQueryCallbacks")
            || ! $baseQuery->beforeQueryCallbacks
        ) {
            return [];
        }

        try {
            $query = clone $baseQuery;
            $query->applyBeforeQueryCallbacks();

            return $query->getJoinedSubqueryTables();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Returns tags for tables reached via whereHas()/whereExists()-style
     * subqueries (recursively, including nested ones).
     */
    protected function getSubqueryWhereTags() : array
    {
        $baseQuery = $this->query;

        if (method_exists($this->query, 'getQuery')) {
            $baseQuery = $this->query->getQuery();
        }

        if (! property_exists($baseQuery, 'wheres')) {
            return [];
        }

        $prefix = $this->getCachePrefix();

        return collect($this->getSubqueryTablesFromBuilder($baseQuery, new SplObjectStorage))
            ->map(function ($table) use ($prefix) {
                return $prefix . (new Str)->slug($table);
            })
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getSubqueryTablesFromBuilder($builder, SplObjectStorage $seen) : array
    {
        if (! is_object($builder) || $seen->contains($builder)) {
            return [];
        }

        $seen->attach($builder);

        $tables = collect($builder->wheres ?? [])
            ->merge($builder->havings ?? [])
            ->flatMap(function ($where) use ($seen) {
                return $this->getSubqueryTablesFromWhere($where, $seen);
            })
            ->toArray();

        foreach ($builder->joins ?? [] as $join) {
            foreach ($join->wheres ?? [] as $where) {
                $tables = array_merge($tables, $this->getSubqueryTablesFromWhere($where, $seen));
            }
        }

        foreach ($builder->unions ?? [] as $union) {
            $unionQuery = $union['query'] ?? null;

            if (is_object($unionQuery)) {
                $tables = array_merge($tables, $this->getSubqueryTablesFromBuilder($unionQuery, $seen));
            }
        }

        return $tables;
    }

    protected function getSubqueryTablesFromWhere(array $where, SplObjectStorage $seen) : array
    {
        $type = $where['type'] ?? null;

        if (! in_array($type, ['Exists', 'NotExists', 'Nested', 'Sub'], true)) {
            return [];
        }

        $query = $where['query'] ?? null;

        if (! is_object($query)) {
            return [];
        }

        $tables = [];
        $from = $query->from ?? null;

        if (is_string($from)) {
            if (stripos($from, ' as ') !== false) {
                $from = trim(explode(' as ', strtolower($from))[0]);
            }

            $tables[] = $from;
        }

        return array_merge($tables, $this->getSubqueryTablesFromBuilder($query, $seen));
    }

    protected function getRelatedModel($carry) : Model
    {
        if ($carry instanceof Relation) {
            return $carry->getQuery()->getModel();
        }

        return $carry;
    }

    protected function getRelation(string $relationName) : ?Relation
    {
        return collect(explode('.', $relationName))
            ->reduce(function ($carry, $name) {
                $carry = $carry ?: $this->model;
                $carry = $this->getRelatedModel($carry);

                if (! method_exists($carry, $name)) {
                    return null;
                }

                $relation = $carry->{$name}();

                // MorphTo cannot be resolved to a concrete type statically;
                // the actual model class depends on the row's morph-type
                // column. Stop the chain here so downstream segments
                // (e.g. "commentable.tags") don't blow up calling a method
                // that only exists on one of the possible morph targets.
                if ($relation instanceof MorphTo) {
                    return null;
                }

                return $relation;
            });
    }

    protected function getMorphToTagsForRelation(string $relationName) : ?array
    {
        $segments = explode('.', $relationName);
        $model = $this->model;

        foreach ($segments as $segment) {
            if (! method_exists($model, $segment)) {
                return null;
            }

            $relation = $model->{$segment}();

            if ($relation instanceof MorphTo) {
                return $this->getMorphToTags($relation);
            }

            if (! ($relation instanceof Relation)) {
                return null;
            }

            $model = $relation->getQuery()->getModel();
        }

        return null;
    }

    protected function getMorphToTags(MorphTo $relation) : array
    {
        $morphMap = Relation::morphMap();

        if (! empty($morphMap)) {
            $tags = [];

            foreach ($morphMap as $type) {
                if (class_exists($type)) {
                    $tags[] = $this->getCachePrefix() . (new Str)->slug($type);
                }
            }

            if (! empty($tags)) {
                return $tags;
            }
        }

        $morphType = $relation->getMorphType();
        $column = last(explode('.', $morphType));
        $table = $relation->getParent()->getTable();

        $types = $relation->getParent()
            ->newQuery()
            ->getQuery()
            ->select($column)
            ->from($table)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column)
            ->toArray();

        $tags = [];

        foreach ($types as $type) {
            $resolved = Relation::getMorphedModel($type) ?? $type;

            if (class_exists($resolved)) {
                $tags[] = $this->getCachePrefix() . (new Str)->slug($resolved);
            }
        }

        return $tags;
    }

    protected function getTagName() : string
    {
        return $this->getCachePrefix()
            . (new Str)->slug(get_class($this->model));
    }

    protected function getTableTagName() : string
    {
        return $this->getCachePrefix()
            . (new Str)->slug($this->model->getTable());
    }
}
