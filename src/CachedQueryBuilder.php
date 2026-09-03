<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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

    /**
     * Tables read by a subquery join, in the order they were joined.
     */
    public function getJoinedSubqueryTables(): array
    {
        return $this->joinedSubqueryTables;
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

    /**
     * Record the table a subquery join reads from, and return the subquery to
     * hand on to Laravel unchanged.
     *
     * A Closure subquery is wrapped rather than resolved, so the caller's
     * callback still runs exactly once, inside Laravel's own createSub().
     */
    protected function recordJoinedSubqueryTable($query)
    {
        if ($query instanceof Closure) {
            return function ($subQuery) use ($query): void {
                $query($subQuery);
                $this->recordJoinedSubqueryTable($subQuery);
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
            $this->joinedSubqueryTables[] = $subQuery->from;
        }

        return $query;
    }
}
