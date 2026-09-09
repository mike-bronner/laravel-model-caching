<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

/**
 * The base query builder for cachable models, which exists to record the table
 * a subquery join reads from.
 *
 * joinSub() compiles its subquery to SQL and hands join() an
 * Illuminate\Database\Query\Expression, so the JoinClause names no table and
 * CacheTags has nothing to tag: a write to that table does not invalidate the
 * cached query, and the next read is served stale. The name is still
 * addressable here, one call before createSub() destroys it.
 *
 * This has to sit at the query-builder layer rather than on CachedBuilder,
 * because Eloquent defers some of its own subquery joins into a beforeQuery()
 * callback — ofMany() is the one in Laravel — and that callback is invoked with
 * the query builder, never with the Eloquent builder wrapping it.
 */
class CachedQueryBuilder extends Builder
{
    /** @var array<int, string> */
    protected array $joinedSubqueryTables = [];

    /** @var array<int, array{table: string, model: Model|null}> */
    protected array $relatedSubqueryTables = [];

    /**
     * Tables read by a subquery join, in the order they were joined.
     */
    public function getJoinedSubqueryTables(): array
    {
        return $this->joinedSubqueryTables;
    }

    /**
     * Tables read by a subquery that Laravel compiled away, with the model that
     * owns each one where the caller could name it.
     *
     * CacheTags recovers most related tables by walking the builders still
     * hanging off `wheres`. Several constraints leave no builder there to walk:
     * a count-constrained `has()` becomes a Basic where holding an Expression,
     * `withCount()`/`withExists()` put their subquery in `columns`, and
     * `whereIn()` replaces a queryable value with an Expression as well. In all
     * three the subquery is compiled to SQL and the builder is dropped, so the
     * table has to be recorded here, while the constraint is being added and
     * the name is still addressable.
     *
     * The model is recorded beside the table because the tag's prefix has to
     * come from the model that will flush it, not from the model being queried
     * (see CachePrefixing::getCachePrefixForModel()). It is null where the
     * constraint named a bare subquery rather than a relation, which is the
     * `whereIn()` case.
     *
     * @return array<int, array{table: string, model: Model|null}>
     */
    public function getRelatedSubqueryTables(): array
    {
        return $this->relatedSubqueryTables;
    }

    public function recordRelatedSubqueryTable(string $table, ?Model $model = null): void
    {
        $this->relatedSubqueryTables[] = [
            "table" => $table,
            "model" => $model,
        ];
    }

    public function whereIn($column, $values, $boolean = "and", $not = false)
    {
        if ($this->isQueryable($values)) {
            $values = $this->recordRelatedWhereSubqueryTable($values);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function joinSub(
        $query,
        $as,
        $first,
        $operator = null,
        $second = null,
        $type = "inner",
        $where = false,
    ) {
        return parent::joinSub(
            $this->recordJoinedSubqueryTable($query),
            $as,
            $first,
            $operator,
            $second,
            $type,
            $where,
        );
    }

    public function leftJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->joinSub($query, $as, $first, $operator, $second, "left");
    }

    public function rightJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->joinSub($query, $as, $first, $operator, $second, "right");
    }

    public function joinLateral($query, string $as, string $type = "inner")
    {
        return parent::joinLateral($this->recordJoinedSubqueryTable($query), $as, $type);
    }

    public function leftJoinLateral($query, string $as)
    {
        return $this->joinLateral($query, $as, "left");
    }

    protected function recordJoinedSubqueryTable($query)
    {
        return $this->recordSubqueryTable($query, function (string $table): void {
            $this->joinedSubqueryTables[] = $table;
        });
    }

    /**
     * Record the table a `whereIn()`-style subquery reads from.
     *
     * The model behind the table is not knowable here: `whereIn()` is handed a
     * builder or a closure, never a relation, so the tag keeps the querying
     * model's prefix. Cross-connection and `$cachePrefix` related models are
     * covered on the relation-driven paths instead, where the model is named.
     */
    protected function recordRelatedWhereSubqueryTable($query)
    {
        return $this->recordSubqueryTable($query, function (string $table): void {
            $this->recordRelatedSubqueryTable($table);
        });
    }

    /**
     * Record the table a subquery reads from, and return the subquery to hand
     * on to Laravel unchanged.
     *
     * Laravel compiles a subquery to SQL and keeps only the resulting
     * Expression, so this is the last point at which the table is addressable
     * at all. A Closure subquery is wrapped rather than resolved, so the
     * caller's callback still runs exactly once, inside Laravel's own
     * createSub().
     */
    protected function recordSubqueryTable($query, Closure $record)
    {
        if ($query instanceof Closure) {
            return function ($subQuery) use ($query, $record): void {
                $query($subQuery);
                $this->recordSubqueryTable($subQuery, $record);
            };
        }

        $subQuery = $query instanceof Relation
            ? $query->getQuery()
            : $query;
        $subQuery = $subQuery instanceof EloquentBuilder
            ? $subQuery->getQuery()
            : $subQuery;

        // `from` is itself an Expression when the subquery selects from another
        // subquery, and a string subquery is raw SQL rather than a table name.
        if (
            $subQuery instanceof Builder
            && is_string($subQuery->from)
        ) {
            $record($subQuery->from);
        }

        return $query;
    }
}
