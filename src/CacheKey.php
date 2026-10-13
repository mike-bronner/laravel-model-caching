<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use BackedEnum;
use DateTimeInterface;
use GeneaLabs\LaravelModelCaching\Traits\CachePrefixing;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Stringable;
use Throwable;
use UnitEnum;

class CacheKey
{
    use CachePrefixing;

    private const KEY_SEPARATOR_ESCAPES = [
        "%" => "%25",
        "-" => "%2D",
        "_" => "%5F",
    ];

    private const KEYED_QUERY_PROPERTIES = [
        "beforeQueryCallbacks",
        "columns",
        "distinct",
        "from",
        "groups",
        "havings",
        "joins",
        "limit",
        "offset",
        "orders",
        "unions",
        "wheres",
    ];

    private const UNKEYED_INFRASTRUCTURE_PROPERTIES = [
        "bindings",
        "bitwiseOperators",
        "connection",
        "grammar",
        "operators",
        "processor",
        "useWritePdo",
    ];

    protected $currentBinding = 0;
    protected $eagerLoad;
    protected $macroKey;
    protected $model;
    protected $query;
    protected $withoutGlobalScopes = [];
    protected $withoutAllGlobalScopes = false;

    public function __construct(
        array $eagerLoad,
        $model,
        $query,
        $macroKey,
        array $withoutGlobalScopes,
        $withoutAllGlobalScopes
    ) {
        $this->eagerLoad = $eagerLoad;
        $this->macroKey = $macroKey;
        $this->model = $model;
        $this->query = $query;
        $this->withoutGlobalScopes = $withoutGlobalScopes;
        $this->withoutAllGlobalScopes = $withoutAllGlobalScopes;
    }

    public function make(
        array $columns = ["*"],
        $idColumn = null,
        string $keyDifferentiator = ""
    ) : string {
        $key = $this->getCachePrefix();
        $key .= $this->getTableSlug();
        $key .= $this->getModelSlug();
        $key .= $this->getIdColumn($idColumn ?: "");
        $key .= $this->getQueryColumns($columns);
        $key .= $this->getDistinctClause();
        $key .= $this->getJoinClauses();
        $key .= $this->getWhereClauses();
        $key .= $this->getGroupByClauses();
        $key .= $this->getHavingClauses();
        $key .= $this->getWithModels();
        $key .= $this->getOrderByClauses();
        $key .= $this->getOffsetClause();
        $key .= $this->getLimitClause();
        $key .= $this->getUnionClauses();
        $key .= $this->getBindingsSlug();
        $key .= $this->getDeferredCallbacksSlug();
        $key .= $this->getUnkeyedQueryPropertiesSlug();
        $key .= $keyDifferentiator;
        $key .= $this->macroKey;

        return $key;
    }

    protected function getDeferredCallbacksSlug() : string
    {
        if (! property_exists($this->query, "beforeQueryCallbacks")
            || ! $this->query->beforeQueryCallbacks
        ) {
            return "";
        }

        try {
            $query = clone $this->query;
            $sql = $query->toSql();

            return "-beforeQuery_" . sha1($sql . $this->encodeForKeyHash($query->getBindings()));
        } catch (Throwable) {
            return "";
        }
    }

    protected function encodeForKeyHash($value) : string
    {
        $encoded = json_encode($value);

        return $encoded === false ? serialize($value) : $encoded;
    }

    protected function getBindingsSlug() : string
    {
        if (! method_exists($this->model, 'query')) {
            return '';
        }

        if ($this->withoutAllGlobalScopes) {
            return Arr::query($this->model->query()->withoutGlobalScopes()->getBindings());
        }

        if (count($this->withoutGlobalScopes) > 0) {
            return Arr::query($this->model->query()->withoutGlobalScopes($this->withoutGlobalScopes)->getBindings());
        }

        return Arr::query($this->model->query()->getBindings());
    }

    protected function getColumnClauses(array $where) : string
    {
        if ($where["type"] !== "Column") {
            return "";
        }

        if ($where["first"] instanceof Expression) {
            $where["first"] = $this->expressionToString($where["first"]);
        }

        if ($where["second"] instanceof Expression) {
	    $where["second"] = $this->expressionToString($where["second"]);
        }

        return $this->getBooleanSlug($where)
            . "-{$where["first"]}_{$where["operator"]}_{$where["second"]}";
    }

    protected function getBooleanSlug(array $where) : string
    {
        $boolean = data_get($where, "boolean", "and");

        return $boolean === "and"
            ? ""
            : "-" . str_replace(" ", "_", $boolean);
    }

    protected function getCurrentBinding(string $type, $bindingFallback = null) : mixed
    {
        return data_get($this->query->bindings, "{$type}.{$this->currentBinding}", $bindingFallback);
    }

    protected function getHavingClauses() : string
    {
        $clauses = Collection::make($this->query->havings)->reduce(function ($carry, $having) {
            $value = $carry;
            $value .= $this->getHavingClause($having);

            return $value;
        });

        return $clauses . $this->getChannelBindingsSlug("having");
    }

    protected function getHavingClause(array $having): string
    {
        return collect($having)
            ->reduce(
                fn (string $carry, $value, $key) => $carry . '_' . $key . '_' . $this->stringifyHavingValue($value),
                '-having',
            );
    }

    private function stringifyHavingValue(mixed $value) : string
    {
        if (is_array($value)) {
            return collect($value)
                ->map($this->stringifyHavingValue(...))
                ->implode("_");
        }

        if ($value instanceof QueryBuilder) {
            return Collection::make($value->havings)
                ->reduce(fn ($carry, $nested) => $carry . $this->getHavingClause($nested), "");
        }

        if (is_bool($value)) {
            return $value ? "1" : "0";
        }

        if ($value === null) {
            return "";
        }

        return str_replace(" ", "_", $this->processEnum($value));
    }

    private function getChannelBindingsSlug(string $channel) : string
    {
        $bindings = data_get($this->query->bindings, $channel, []);

        if (! $bindings) {
            return "";
        }

        return "-{$channel}Bindings_" . collect($bindings)
            ->map($this->stringifyBinding(...))
            ->implode("_");
    }

    protected function getIdColumn(string $idColumn) : string
    {
        return $idColumn ? "_{$idColumn}" : "";
    }

    protected function getInAndNotInClauses(array $where) : string
    {
        if (! in_array($where["type"], ["In", "NotIn", "InRaw", "NotInRaw"])) {
            return "";
        }

        $type = strtolower($where["type"]);
        $booleanSlug = $this->getBooleanSlug($where);
        $subquery = $this->getValuesFromWhere($where);
        $bindingCount = $this->getInClauseBindingCount($where);

        if (
            ! is_numeric($subquery)
            && ! is_numeric(str_replace("_", "", $subquery))
            && strlen($subquery) === 16
        ) {
            try {
                $subquery = Uuid::fromBytes($subquery);
                $values = $this->recursiveImplode([$subquery], "_");
                $this->currentBinding += $bindingCount;

                return "{$booleanSlug}-{$where["column"]}_{$type}{$values}";
            } catch (Throwable) {
            }
        }

        $subquery = $this->getValuesFromWhere($where, escape: true);
        $placeholderCount = preg_match_all('/\?(?=(?:[^"]*"[^"]*")*[^"]*\Z)/m', $subquery);

        if ($placeholderCount === 0) {
            $this->currentBinding += $bindingCount;

            $values = $this->recursiveImplode([$subquery], "_");

            return "{$booleanSlug}-{$where["column"]}_{$type}{$values}";
        }

        $values = collect(data_get($this->query->bindings, "where"))
            ->slice($this->currentBinding, $placeholderCount)
            ->values()
            ->map(function ($binding) {
                return $this->stringifyBinding($binding);
            });
        $this->currentBinding += $placeholderCount;

        $subquery = preg_replace('/\?(?=(?:[^"]*"[^"]*")*[^"]*\Z)/m', "_??_", $subquery);
        $subquery = str_replace('%', '%%', $subquery);
        $subquery = collect(vsprintf(str_replace("_??_", "%s", $subquery), $values->toArray()));
        $values = $this->recursiveImplode($subquery->toArray(), "_");

        return "{$booleanSlug}-{$where["column"]}_{$type}{$values}";
    }

    protected function getDistinctClause() : string
    {
        if (! property_exists($this->query, "distinct")
            || ! $this->query->distinct
        ) {
            return "";
        }

        if (! is_array($this->query->distinct)) {
            return "-distinct";
        }

        return "-distinct_" . collect($this->query->distinct)
            ->map($this->expressionToString(...))
            ->implode("_");
    }

    protected function getGroupByClauses() : string
    {
        if (! property_exists($this->query, "groups")
            || ! $this->query->groups
        ) {
            return "";
        }

        $groups = collect($this->query->groups)
            ->map($this->expressionToString(...))
            ->implode("_");

        return "-groupBy_" . $groups
            . $this->getChannelBindingsSlug("groupBy");
    }

    protected function getJoinClauses() : string
    {
        if (! property_exists($this->query, "joins")
            || ! $this->query->joins
        ) {
            return "";
        }

        $joins = collect($this->query->joins)
            ->map(fn ($join) => $join->type
                . "_" . $this->expressionToString($join->table)
                . "_" . substr(
                    sha1($this->encodeForKeyHash($this->normalizeForHash($join->wheres))),
                    0,
                    12,
                ))
            ->implode("_");

        return "-join_" . $joins
            . $this->getChannelBindingsSlug("join");
    }

    protected function getUnionClauses() : string
    {
        if (! property_exists($this->query, "unions")
            || ! $this->query->unions
        ) {
            return "";
        }

        $unions = collect($this->query->unions)
            ->map(fn ($union) => (data_get($union, "all") ? "all_" : "")
                . sha1(
                    $union["query"]->toSql()
                    . $this->encodeForKeyHash($union["query"]->getBindings())
                ))
            ->implode("_");

        return "-union_" . $unions;
    }

    private function getInClauseBindingCount(array $where) : int
    {
        if (in_array($where["type"], ["InRaw", "NotInRaw"], true)) {
            return 0;
        }

        return count($this->query->cleanBindings(data_get($where, "values", [])));
    }

    protected function getLimitClause() : string
    {
        if (! property_exists($this->query, "limit")
            || ! $this->query->limit
        ) {
            return "";
        }

        return "-limit_{$this->query->limit}";
    }

    protected function getModelSlug() : string
    {
        return (new Str)->slug(get_class($this->model));
    }

    protected function getNestedClauses(array $where) : string
    {
        if (! in_array($where["type"], ["Exists", "Nested", "NotExists"])) {
            return "";
        }

        return $this->getBooleanSlug($where)
            . "-"
            . strtolower($where["type"])
            . $this->getWhereClauses($where["query"]->wheres);
    }

    protected function getOffsetClause() : string
    {
        if (! property_exists($this->query, "offset")
            || ! $this->query->offset
        ) {
            return "";
        }

        return "-offset_{$this->query->offset}";
    }

    protected function getOrderByClauses() : string
    {
        if (! property_exists($this->query, "orders")
            || ! $this->query->orders
        ) {
            return "";
        }

        $orders = collect($this->query->orders);

        return $orders
            ->reduce(function ($carry, $order) {
                if (($order["type"] ?? "") === "Raw") {
                    return $carry . "_orderByRaw_" . (new Str)->slug($order["sql"]);
                }

                return sprintf(
                    '%s_orderBy_%s_%s',
                    $carry,
                    $this->expressionToString($order["column"]),
                    $order["direction"]
                );
            })
            ?: "";
    }

    protected function getRowValuesClauses(array $where) : string
    {
        if ($where["type"] !== "RowValues") {
            return "";
        }

        $columns = implode("_", $where["columns"]);
        $operator = str_replace(" ", "_", $where["operator"]);
        $values = collect($where["values"])
            ->map(function ($value) {
                return $this->escapeKeySegment($this->processEnum($value));
            })
            ->implode("_");

        $this->currentBinding += count($this->query->cleanBindings($where["values"]));

        return $this->getBooleanSlug($where)
            . "-{$columns}_{$operator}_{$values}";
    }

    protected function getOtherClauses(array $where) : string
    {
        if (in_array($where["type"], ["Exists", "Nested", "NotExists", "Column", "raw", "In", "NotIn", "InRaw", "NotInRaw", "RowValues"])) {
            return "";
        }

        $value = $this->getTypeClause($where);
        $value .= $this->getValuesClause($where);

        $column = "";

	if (data_get($where, "column") instanceof Expression) {
            $where["column"] = $this->expressionToString(data_get($where, "column"));
        }

        $column .= isset($where["column"]) ? $where["column"] : "";
        $column .= isset($where["columns"]) ? implode("-", $where["columns"]) : "";

        return $this->getBooleanSlug($where) . "-{$column}_{$value}";
    }

    protected function getQueryColumns(array $columns) : string
    {
        if (($columns === ["*"]
                || $columns === [])
            && (! property_exists($this->query, "columns")
                || ! $this->query->columns)
        ) {
            return "";
        }

        if (property_exists($this->query, "columns")
            && $this->query->columns
        ) {
            return "_" . collect($this->query->columns)
                ->map(function ($column) {
                    return $this->expressionToString($column);
                })
                ->implode("_");
        }

        return "_" . collect($columns)
            ->map(function ($column) {
                return $this->expressionToString($column);
            })
            ->implode("_");
    }

    protected function getRawClauses(array $where) : string
    {
        if (! in_array($where["type"], ["raw"])) {
            return "";
        }

        $segments = explode("?", $where["sql"]);
        $clause = array_shift($segments);

        foreach ($segments as $segment) {
            $clause .= $this->getCurrentBinding("where") . $segment;
            $this->currentBinding++;
        }

        return $this->getBooleanSlug($where)
            . "-"
            . str_replace(" ", "_", $clause);
    }

    protected function getTableSlug() : string
    {
        return (new Str)->slug($this->query->from)
            . ":";
    }

    protected function getTypeClause($where) : string
    {
        $segments = $where["type"] === "Basic"
            ? []
            : [strtolower($where["type"])];

        if (data_get($where, "operator") !== null) {
            $segments[] = strtolower($where["operator"]);
        }

        if ($segments === []) {
            $segments[] = strtolower($where["type"]);
        }

        if (data_get($where, "not", false)) {
            array_unshift($segments, "not");
        }

        return str_replace(" ", "_", implode("_", $segments));
    }

    protected function getValuesClause(array $where = []) : string
    {
        if (! $where
            || in_array($where["type"], ["NotNull", "Null"])
        ) {
            return "";
        }

        $values = $this->getValuesFromWhere($where);
        $values = $this->getValuesFromBindings($where, $values);

        return "_" . $values;
    }

    protected function getValuesFromWhere(array $where, bool $escape = false) : string
    {
        if (is_array((new Arr)->get($where, "values"))) {
            $values = collect($where["values"])->flatten()->toArray();
            return implode("_", $this->processEnums($values, $escape));
        }

        if (is_array((new Arr)->get($where, "value"))) {
            $values = collect($where["value"])->flatten()->toArray();
            return implode("_", $this->processEnums($values, $escape));
        }

        $value = (new Arr)->get($where, "value", "");

        return implode("_", $this->processEnums([$value], $escape));
    }

    protected function getValuesFromBindings(array $where, string $values) : string
    {
        $bindingFallback = __CLASS__ . ':UNKNOWN_BINDING';
        $currentBinding = $this->getCurrentBinding("where", $bindingFallback);

        if ($currentBinding === $bindingFallback) {
            return $values;
        }

        if (data_get($where, "value") instanceof Expression) {
            return $values;
        }

        $this->currentBinding++;
        $values = $this->stringifyBinding($currentBinding);

        if ($where["type"] === "between"
            && count($this->query->cleanBindings(data_get($where, "values", []))) > 1
        ) {
            $values .= "_" . $this->stringifyBinding($this->getCurrentBinding("where"));
            $this->currentBinding++;
        }

        return $values;
    }

    protected function getWhereClauses(?array $wheres = null) : string
    {
        return "" . $this->getWheres($wheres)
            ->reduce(function ($carry, $where) {
                $value = $carry;
                $value .= $this->getNestedClauses($where);
                $value .= $this->getColumnClauses($where);
                $value .= $this->getRawClauses($where);
                $value .= $this->getInAndNotInClauses($where);
                $value .= $this->getRowValuesClauses($where);
                $value .= $this->getOtherClauses($where);

                return $value;
            });
    }

    protected function getWheres(?array $wheres) : Collection
    {
        if ($wheres !== null) {
            return collect($wheres);
        }

        return collect(property_exists($this->query, "wheres")
            ? $this->query->wheres
            : []);
    }

    protected function getWithModels() : string
    {
        $eagerLoads = collect($this->eagerLoad);

        if ($eagerLoads->isEmpty()) {
            return "";
        }

        return $eagerLoads->reduce(function ($carry, $constraint, $related) {
            if (! method_exists($this->model, $related)) {
                $carry .= "-{$related}";
            } else {
                $relatedModel = $this->model->$related()->getRelated();
                $relatedConnection = $relatedModel->getConnection()->getName();
                $relatedDatabase = $relatedModel->getConnection()->getDatabaseName();

                $carry .= "-{$relatedConnection}:{$relatedDatabase}:{$related}";
            }

            $carry .= $this->getEagerLoadConstraintKey($related, $constraint);

            return $carry;
        }, "");
    }

    protected function getEagerLoadConstraintKey(string $related, $constraint) : string
    {
        if (! ($constraint instanceof \Closure)) {
            return "";
        }

        if (
            ! method_exists($this->model, $related)
            && ! $this->model->isRelation($related)
        ) {
            return "";
        }

        try {
            $freshModel = (new \ReflectionClass($this->model))->newInstanceWithoutConstructor();
            $relation = $freshModel->$related();
            $baseWheres   = $relation->getQuery()->getQuery()->wheres ?? [];
            $baseBindings = $relation->getQuery()->getQuery()->bindings['where'] ?? [];
            $constraint($relation);
            $afterWheres   = $relation->getQuery()->getQuery()->wheres ?? [];
            $afterBindings = $relation->getQuery()->getQuery()->bindings['where'] ?? [];
            $addedWheres   = array_slice($afterWheres,   count($baseWheres));
            $addedBindings = array_slice($afterBindings, count($baseBindings));

            if (empty($addedWheres)) {
                return "";
            }

            return "=" . sha1($this->encodeForKeyHash($addedWheres) . $this->encodeForKeyHash($addedBindings));
        } catch (Throwable) {
            return "";
        }
    }

    protected function recursiveImplode(array $items, string $glue = ",") : string
    {
        return collect($items)
            ->reduce(function (string $result, $value) use ($glue) {
                if (is_string($value)) {
                    $value = str_replace('"', '', $value);
                    $value = explode(" ", $value);

                    if (count($value) === 1) {
                        $value = $value[0];
                    }
                }

                if (is_array($value)) {
                    return $result . $this->recursiveImplode($value, $glue);
                }

                return $result . $glue . $value;
            }, "");
    }

    private function stringifyBinding(mixed $binding) : string
    {
        if ($binding instanceof DateTimeInterface) {
            $binding = $binding->format("Y-m-d-H-i-s");
        }

        return $this->escapeKeySegment((string) $binding);
    }

    private function escapeKeySegment(string $segment) : string
    {
        return strtr($segment, self::KEY_SEPARATOR_ESCAPES);
    }

    private function getUnkeyedQueryPropertiesSlug() : string
    {
        $unkeyed = Collection::make(get_object_vars($this->query))
            ->except(collect(self::KEYED_QUERY_PROPERTIES)
                ->merge(self::UNKEYED_INFRASTRUCTURE_PROPERTIES))
            ->reject(fn ($value) => $value === null || $value === false || $value === [])
            ->map($this->normalizeForHash(...))
            ->sortKeys()
            ->all();

        if (! $unkeyed) {
            return "";
        }

        return "-q" . substr(sha1($this->encodeForKeyHash($unkeyed)), 0, 12);
    }

    private function normalizeForHash(mixed $value) : mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map($this->normalizeForHash(...))
                ->all();
        }

        if ($value instanceof Expression) {
            return $this->expressionToString($value);
        }

        if ($value instanceof \Closure) {
            return "Closure";
        }

        if ($value instanceof QueryBuilder) {
            return $value->toSql() . $this->encodeForKeyHash($value->getBindings());
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return $value;
    }

    // A where() value is whatever the application passed, so the parameter
    // cannot name the shapes it accepts: anything the union left out raised a
    // TypeError on a query this class only means to be keying.
    // whereJsonContains() is the plainest way to reach it, because it keeps the
    // value it is given and encodes it only when the statement is compiled, so
    // a Collection arrives here intact.
    private function processEnum(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        } elseif ($value instanceof UnitEnum) {
            return $value->name;
        } elseif ($value instanceof Expression) {
            return $this->expressionToString($value);
        } elseif ($value instanceof DateTimeInterface) {
            return $value->format("Y-m-d-H-i-s");
        }

        // An object with no string form cannot be interpolated below, and PHP
        // implements Stringable automatically for every class declaring
        // __toString(), so this covers the rest.
        if (is_object($value) && ! $value instanceof Stringable) {
            return $this->hashOpaqueObject($value);
        }

        return "{$value}";
    }

    // The segment still has to tell two values apart, because a where() value
    // decides which rows come back. The class name alone does not: two
    // Collections holding different ids would share a key and one would be
    // served the other's rows. serialize() reads private state, so it separates
    // them, and it is stable across requests, which a key has to be.
    //
    // It throws on a Closure and on anything holding a PDO connection. There is
    // no honest way to tell such a value from another of its class, so the
    // fallback is unique per instance and never repeats: the key misses instead
    // of returning rows that belong to a different query.
    private function hashOpaqueObject(object $value): string
    {
        try {
            $identity = serialize($value);
        } catch (Throwable) {
            // this will increase cache writes but beats failing outright. Every
            // value that matches before this will keep working so this is a worst
            // case fallback and there is currently no "do not cache" signal
            $identity = spl_object_hash($value);
        }

        return $value::class . "-" . sha1($identity);
    }

    private function processEnums(array $values, bool $escape = false): array
    {
        return collect($values)
            ->map(fn ($value) => $escape
                ? $this->escapeKeySegment($this->processEnum($value))
                : $this->processEnum($value))
            ->all();
    }

    private function expressionToString(Expression|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) $value->getValue($this->query->getConnection()->getQueryGrammar());
    }
}
