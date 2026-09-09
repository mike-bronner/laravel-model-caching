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

                $relatedModel = $relation->getQuery()->getModel();

                return [$this->getCachePrefixForModel($relatedModel)
                    . (new Str)->slug(get_class($relatedModel))];
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

    /**
     * The query builder the tags are read from.
     *
     * `$this->query` is an Eloquent builder on the read path and a plain query
     * builder on the write path, and only the latter carries `joins`, `wheres`,
     * and the recorded subquery tables.
     */
    protected function resolveBaseQuery() : mixed
    {
        if (method_exists($this->query, 'getQuery')) {
            return $this->query->getQuery();
        }

        return $this->query;
    }

    /**
     * Strip a table alias, e.g. "products as p" -> "products".
     */
    protected function stripTableAlias(string $table) : string
    {
        if (stripos($table, ' as ') === false) {
            return $table;
        }

        return trim(explode(' as ', strtolower($table))[0]);
    }

    /**
     * Tags for the tables named by the query's own joins.
     *
     * A join names a bare table and no model, so unlike the subquery tags
     * below these keep the querying model's cache prefix. A joined table
     * belonging to a model on another connection, or to one declaring its own
     * `$cachePrefix`, is therefore still tagged under the wrong prefix and its
     * write does not bust this query. Closing that needs a table-name to model
     * lookup, which cannot be made reliable: two models may map to one table.
     */
    protected function getJoinTags() : array
    {
        $baseQuery = $this->resolveBaseQuery();

        return $this->makeTableTags(collect($baseQuery->joins ?? [])
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

                return $this->stripTableAlias($table);
            })
            ->merge($this->getJoinedSubqueryTables($baseQuery))
            ->filter(function ($table) {
                return $table !== null;
            })
            ->map(function ($table) {
                return ["table" => $table, "model" => null];
            })
            ->toArray());
    }

    /**
     * Turn table entries into tags.
     *
     * A null model means the caller could not identify one, and the querying
     * model's prefix stands in — which is correct only while that model shares
     * a connection and a `$cachePrefix` with the table's real owner.
     *
     * @param  array<int, array{table: string, model: Model|null}>  $entries
     */
    protected function makeTableTags(array $entries) : array
    {
        return collect($entries)
            ->map(fn (array $entry) => $this->getCachePrefixForModel($entry["model"] ?: $this->model)
                . (new Str)->slug($entry["table"]))
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
     * Tags for tables reached through a subquery, recursively.
     *
     * Two sources feed this. Subqueries Laravel still holds as builders —
     * whereHas()/whereExists() and friends — are walked out of `wheres`, and
     * name a table but no model. Subqueries Laravel compiled to an Expression
     * and discarded were recorded on the builder as the constraint was added
     * (CachedQueryBuilder::getRelatedSubqueryTables()), and most of those name
     * the owning model as well.
     *
     * Where a table arrives from both, the recorded entry wins and the walked
     * one is dropped: they name the same table, but only the recorded one
     * carries the model whose write has to bust this query, and so only it can
     * build the prefix that model flushes under.
     */
    protected function getSubqueryWhereTags() : array
    {
        $entries = $this->getSubqueryTablesFromBuilder(
            $this->resolveBaseQuery(),
            new SplObjectStorage,
        );
        $modelledTables = collect($entries)
            ->filter(fn (array $entry) => $entry["model"] !== null)
            ->pluck("table")
            ->flip();

        return $this->makeTableTags(collect($entries)
            ->reject(fn (array $entry) => $entry["model"] === null
                && $modelledTables->has($entry["table"]))
            ->values()
            ->toArray());
    }

    /**
     * @return array<int, array{table: string, model: Model|null}>
     */
    protected function getSubqueryTablesFromBuilder($builder, SplObjectStorage $seen) : array
    {
        if (! is_object($builder) || $seen->contains($builder)) {
            return [];
        }

        $seen->attach($builder);

        $tables = method_exists($builder, "getRelatedSubqueryTables")
            ? $builder->getRelatedSubqueryTables()
            : [];

        // `havings` is deliberately not walked. Of the six having types
        // Illuminate\Database\Query\Builder builds, only the nested one carries
        // a builder, and that builder comes from forNestedWhere(), which copies
        // `from` off the outer query. A nested having can therefore only ever
        // name the table already tagged by getTableTagName(), so walking it
        // cannot produce a tag, and no test can tell the walk from its absence.
        $tables = array_merge(
            $tables,
            collect($builder->wheres ?? [])
                ->flatMap(function ($where) use ($seen) {
                    return $this->getSubqueryTablesFromWhere($where, $seen);
                })
                ->toArray(),
        );

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

    /**
     * @return array<int, array{table: string, model: Model|null}>
     */
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
            $tables[] = [
                "table" => $this->stripTableAlias($from),
                "model" => null,
            ];
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
