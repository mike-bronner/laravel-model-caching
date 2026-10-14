<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use WeakMap;

class CachedQueryBuilder extends Builder
{
    protected array $joinedSubqueryTables = [];

    protected array $relatedSubqueryTables = [];

    protected static ?WeakMap $recorders = null;

    protected static ?WeakMap $recordingMacros = null;

    protected array $subqueryArguments = [
        "joinSub" => [0, "query", "recordJoinedSubqueryTable"],
        "leftJoinSub" => [0, "query", "recordJoinedSubqueryTable"],
        "rightJoinSub" => [0, "query", "recordJoinedSubqueryTable"],
        "crossJoinSub" => [0, "query", "recordJoinedSubqueryTable"],
        "straightJoinSub" => [0, "query", "recordJoinedSubqueryTable"],
        "joinLateral" => [0, "query", "recordJoinedSubqueryTable"],
        "leftJoinLateral" => [0, "query", "recordJoinedSubqueryTable"],
        "fromSub" => [0, "query", "recordRelatedWhereSubqueryTable"],
        "whereIn" => [1, "values", "recordRelatedWhereSubqueryTable"],
        "orWhereIn" => [1, "values", "recordRelatedWhereSubqueryTable"],
        "whereNotIn" => [1, "values", "recordRelatedWhereSubqueryTable"],
        "orWhereNotIn" => [1, "values", "recordRelatedWhereSubqueryTable"],
    ];

    public static function fromBase(Builder $query): Builder
    {
        if ($query::class !== Builder::class) {
            return $query;
        }

        $builder = new self(
            $query->getConnection(),
            $query->getGrammar(),
            $query->getProcessor(),
        );

        foreach (get_object_vars($query) as $property => $value) {
            $builder->{$property} = $value;
        }

        return $builder;
    }

    public static function recorderFor(Builder $query, ?Builder $clonedFrom = null): self
    {
        if ($query instanceof self) {
            return $query;
        }

        static::$recorders ??= new WeakMap;

        static::$recorders[$query] ??= new self($query->getConnection());

        if (
            $clonedFrom
            && isset(static::$recorders[$clonedFrom])
        ) {
            $source = static::$recorders[$clonedFrom];
            $recorder = static::$recorders[$query];
            $recorder->mergeRecordsOf($source);
        }

        return static::$recorders[$query];
    }

    public function recordSubqueryCall(string $method, array $parameters): array
    {
        $arguments = array_change_key_case($this->subqueryArguments);
        $unknown = [null, null, null];
        [$position, $name, $recorder] = collect($arguments)
            ->get(strtolower($method), $unknown);

        if (! $recorder) {
            return $parameters;
        }

        $key = collect([$position, $name])
            ->first(fn (int|string $key) => array_key_exists($key, $parameters));

        if ($key === null) {
            return $parameters;
        }

        return array_replace($parameters, [
            $key => $this->{$recorder}(data_get($parameters, $key)),
        ]);
    }

    public function recordSubqueryCallsOf(EloquentBuilder $builder): EloquentBuilder
    {
        static::$recordingMacros ??= new WeakMap;

        collect(array_keys($this->subqueryArguments))
            ->each(function (string $method) use ($builder): void {
                $macro = $builder->hasMacro($method)
                    ? $builder->getMacro($method)
                    : null;

                if ($this->isRecordingMacro($macro)) {
                    return;
                }

                $recordingMacro = $this->recordingMacro($method, $macro);
                static::$recordingMacros[$recordingMacro] = true;
                $builder->macro($method, $recordingMacro);
            });

        return $builder;
    }

    protected function mergeRecordsOf(self $recorder): void
    {
        $this->joinedSubqueryTables = [
            ...$recorder->joinedSubqueryTables,
            ...$this->joinedSubqueryTables,
        ];
        $this->relatedSubqueryTables = [
            ...$recorder->relatedSubqueryTables,
            ...$this->relatedSubqueryTables,
        ];
    }

    protected function isRecordingMacro(mixed $macro): bool
    {
        return $macro instanceof Closure
            && isset(static::$recordingMacros[$macro]);
    }

    protected function recordingMacro(string $method, mixed $consumerMacro): Closure
    {
        return function (
            EloquentBuilder $builder,
            mixed ...$parameters,
        ) use (
            $method,
            $consumerMacro,
        ): mixed {
            $parameters = CachedQueryBuilder::recorderFor($builder->getQuery())
                ->recordSubqueryCall($method, $parameters);

            if ($consumerMacro) {
                return $consumerMacro($builder, ...$parameters);
            }

            return $this->forwardSubqueryCall($builder, $method, $parameters);
        };
    }

    protected function globalMacro(string $method): mixed
    {
        return EloquentBuilder::getGlobalMacro($method);
    }

    protected function forwardSubqueryCall(
        EloquentBuilder $builder,
        string $method,
        array $parameters,
    ): mixed {
        if (EloquentBuilder::hasGlobalMacro($method)) {
            $macro = $this->globalMacro($method);
            $macro = $macro instanceof Closure
                ? $macro->bindTo($builder, $builder::class)
                : $macro;

            return $macro(...$parameters);
        }

        if ($builder->hasNamedScope($method)) {
            return $builder->scopes([$method => $parameters]);
        }

        $query = $builder->getQuery();
        $query->{$method}(...$parameters);

        return $builder;
    }

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

    // The subquery's table is tagged with the querying model's prefix, so a source model
    // on another connection or with its own $cachePrefix is not flushed by its own writes.
    // fromRaw() is not tagged, because its table name is inside a raw string.
    public function fromSub($query, $as): static
    {
        return parent::fromSub($this->recordRelatedWhereSubqueryTable($query), $as);
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

    public function crossJoinSub($query, $as): static
    {
        return parent::crossJoinSub($this->recordJoinedSubqueryTable($query), $as);
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
