<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

class CachedQueryBuilder extends Builder
{
    protected array $joinedSubqueryTables = [];

    protected array $relatedSubqueryTables = [];

    public function getJoinedSubqueryTables(): array
    {
        return $this->joinedSubqueryTables;
    }

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

    public function whereIn($column, $values, $boolean = "and", $not = false): static
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
    ): static {
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

    public function leftJoinSub($query, $as, $first, $operator = null, $second = null): static
    {
        return $this->joinSub($query, $as, $first, $operator, $second, "left");
    }

    public function rightJoinSub($query, $as, $first, $operator = null, $second = null): static
    {
        return $this->joinSub($query, $as, $first, $operator, $second, "right");
    }

    public function joinLateral($query, string $as, string $type = "inner"): static
    {
        return parent::joinLateral($this->recordJoinedSubqueryTable($query), $as, $type);
    }

    public function leftJoinLateral($query, string $as): static
    {
        return $this->joinLateral($query, $as, "left");
    }

    protected function recordJoinedSubqueryTable($query): mixed
    {
        return $this->recordSubqueryTable($query, function (string $table): void {
            $this->joinedSubqueryTables[] = $table;
        });
    }

    protected function recordRelatedWhereSubqueryTable($query): mixed
    {
        return $this->recordSubqueryTable($query, function (string $table): void {
            $this->recordRelatedSubqueryTable($table);
        });
    }

    protected function recordSubqueryTable($query, Closure $record): mixed
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

        if (
            $subQuery instanceof Builder
            && is_string($subQuery->from)
        ) {
            $record($subQuery->from);
        }

        return $query;
    }
}
