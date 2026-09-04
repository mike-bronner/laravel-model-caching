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
use Throwable;
use UnitEnum;

class CacheKey
{
    use CachePrefixing;

    // The key format joins its segments with "-" and "_", so a value holding
    // either character is ambiguous: where("title", "a-title_=_b") builds the
    // same key as where("title", "a")->where("title", "b"). Percent-encoding
    // the separators removes that ambiguity. "%" is encoded as well, because an
    // encoding whose escape character is itself unescaped is not reversible:
    // without it the value "a%2Db" and the value "a-b" both come out as
    // "a%2Db". strtr() rewrites only the characters it finds, so a value
    // carrying none of the three keeps its exact previous bytes.
    //
    // strtr() with an array scans the subject once and never re-reads what it
    // wrote, so it cannot encode its own output and the order of the pairs
    // below does not matter to it. It matters to any reimplementation that
    // replaces sequentially: a str_replace() loop that reaches "%" last encodes
    // the "%" of the "%2D" it just wrote and turns "-" into "%252D".
    // WhereValueEscapingTest::testSeparatorEncodingIsNotDoubleEncoded pins the
    // output, because nothing else here would fail if this were rewritten.
    private const KEY_SEPARATOR_ESCAPES = [
        "%" => "%25",
        "-" => "%2D",
        "_" => "%5F",
    ];

    // Query state that a named method above writes into the key. Anything the
    // builder carries that is in neither this list nor the one below is hashed
    // by getUnkeyedQueryPropertiesSlug().
    //
    // The split exists because enumeration is what has failed here repeatedly.
    // groupBy, joins, unions and distinct were each absent from the key, and
    // each returned another query's rows, because no method named them and
    // nothing noticed. Listing what *is* handled inverts that: a property this
    // class has never heard of produces an over-specific key, which costs a
    // cache miss, rather than a shared key, which returns wrong rows.
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

    // Builder state with no bearing on which rows come back: connection
    // plumbing, the grammar's operator tables, and the binding array the
    // clause walkers already read through their own channels.
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

        // Queries like Eloquent's `ofMany()` relations build their joined
        // subquery (and its bindings) lazily through `beforeQuery` callbacks,
        // which only run at execution time — after this key was generated.
        // Materialize them on a clone so the key reflects the SQL that will
        // actually run, without mutating the query Laravel executes.
        //
        // Boundary notes: the callbacks are shared by reference with the
        // original query, so materializing here means they run once per key
        // computation and again at execution — harmless for Eloquent's
        // idempotent, self-clearing `ofMany()` family, but a custom
        // non-idempotent `beforeQuery` callback would observe the extra
        // invocation. The shallow clone also only isolates this outer query:
        // a composite `ofMany(["a", "b"])` subquery carries its own nested
        // `beforeQuery` callback on a shared subquery object.
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

        // json_encode() returns false — not a string — when any nested value
        // is a non-UTF-8 byte string (e.g. a raw binary(16) UUID key), which
        // would silently erase the value from the hash and let differing
        // queries collide on one cache key. serialize() is binary-safe;
        // valid-UTF-8 values keep the exact json_encode() output so existing
        // cache keys are unchanged.
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

    // Every clause family emits its boolean the same way: nothing for the
    // default "and", and a "-{boolean}" prefix otherwise. Without it,
    // whereNot("id", 1) (boolean "and not") and orWhere("id", 1) collapse onto
    // the same key as where("id", 1), so an "exclude" query reads the cached
    // "only" result. Omitting the default keeps every plain where() key
    // byte-identical to the keys this package built before.
    protected function getBooleanSlug(array $where) : string
    {
        $boolean = data_get($where, "boolean", "and");

        return $boolean === "and"
            ? ""
            : "-" . str_replace(" ", "_", $boolean);
    }

    protected function getCurrentBinding(string $type, $bindingFallback = null)
    {
        return data_get($this->query->bindings, "{$type}.{$this->currentBinding}", $bindingFallback);
    }

    protected function getHavingClauses()
    {
        $clauses = Collection::make($this->query->havings)->reduce(function ($carry, $having) {
            $value = $carry;
            $value .= $this->getHavingClause($having);

            return $value;
        });

        // havingRaw("total > ?", [5]) stores only its SQL in the clause array
        // and puts the 5 in the "having" binding channel, so two calls that
        // differ only in their bindings build the same clause string.
        return $clauses . $this->getChannelBindingsSlug("having");
    }

    protected function getHavingClause(array $having): string
    {
        $return = '-having';

        foreach ($having as $key => $value) {
            $return .= '_' . $key . '_' . $this->stringifyHavingValue($value);
        }

        return $return;
    }

    // A having clause carries whatever shape its type needs: a scalar for
    // having(), a two-element array for havingBetween(), a bool for the "not"
    // flag, a nested Builder for having(Closure). Passing each of those to
    // str_replace() assumed they were all strings, so having("total", ">", 5)
    // raised a TypeError and havingBetween() folded both bounds into the
    // literal "Array", giving every range the same key.
    //
    // Strings keep the exact space-to-underscore rewrite they had before, so
    // every havingRaw() key that worked stays byte-identical.
    private function stringifyHavingValue(mixed $value) : string
    {
        if (is_array($value)) {
            return implode("_", array_map($this->stringifyHavingValue(...), $value));
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

    // Bindings that live in a channel of their own rather than in "where".
    // The clause arrays for those channels hold placeholders, not values, so
    // the values reach the key only from here.
    private function getChannelBindingsSlug(string $channel) : string
    {
        $bindings = data_get($this->query->bindings, $channel, []);

        if (! $bindings) {
            return "";
        }

        return "-{$channel}Bindings_" . implode(
            "_",
            array_map($this->stringifyBinding(...), $bindings),
        );
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
                // whereIn() bound the raw UUID, so the cursor owes it a slot
                // whichever branch renders the clause. Returning without
                // advancing left every later clause reading one binding early.
                $this->currentBinding += $bindingCount;

                return "{$booleanSlug}-{$where["column"]}_{$type}{$values}";
            } catch (Throwable) {
                // do nothing
            }
        }

        // Escaping starts here, below the branch above, on purpose: that branch
        // recognises a raw binary UUID by being exactly 16 bytes long, and
        // encoding a 0x2D or 0x5F byte inside one would stretch it past 16 and
        // send the value down the wrong path. Everything from here on renders
        // values straight into the key, so from here on they are escaped —
        // whereIn("id", ["1_2"]) and whereIn("id", [1, 2]) no longer collide.
        // The escaping is redone from the individual values rather than applied
        // to the string above, which is already joined on "_" and would lose
        // the join itself.
        $subquery = $this->getValuesFromWhere($where, escape: true);
        $placeholderCount = preg_match_all('/\?(?=(?:[^"]*"[^"]*")*[^"]*\Z)/m', $subquery);

        if ($placeholderCount === 0) {
            $this->currentBinding += $bindingCount;

            $values = $this->recursiveImplode([$subquery], "_");

            return "{$booleanSlug}-{$where["column"]}_{$type}{$values}";
        }

        // The bindings substituted in below land in the key verbatim, so they
        // are escaped for the same reason the values above are. vsprintf()
        // reads "%" only in its format string, never in the arguments, so an
        // encoded value passes through it untouched.
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

        // distinct() sets true. distinct("a", "b") sets the column list.
        if (! is_array($this->query->distinct)) {
            return "-distinct";
        }

        return "-distinct_" . implode("_", array_map(
            $this->expressionToString(...),
            $this->query->distinct,
        ));
    }

    protected function getGroupByClauses() : string
    {
        if (! property_exists($this->query, "groups")
            || ! $this->query->groups
        ) {
            return "";
        }

        // Column names are not escaped, here or in getOrderByClauses() and
        // getQueryColumns(). Escaping is for bound values, which an
        // application controls outright; an identifier comes from the
        // developer's own code.
        $groups = array_map(
            $this->expressionToString(...),
            $this->query->groups,
        );

        return "-groupBy_" . implode("_", $groups)
            . $this->getChannelBindingsSlug("groupBy");
    }

    protected function getJoinClauses() : string
    {
        if (! property_exists($this->query, "joins")
            || ! $this->query->joins
        ) {
            return "";
        }

        // The join type separates join() from leftJoin() on one table, and the
        // hashed ON conditions separate two joins of the same table on
        // different columns. CacheTags already tags the joined table, but a
        // tag is a namespace and not a key: two joins landing in the same
        // namespace still need different keys.
        $joins = array_map(
            fn ($join) => $join->type
                . "_" . $this->expressionToString($join->table)
                . "_" . substr(
                    sha1($this->encodeForKeyHash($this->normalizeForHash($join->wheres))),
                    0,
                    12,
                ),
            $this->query->joins,
        );

        return "-join_" . implode("_", $joins)
            . $this->getChannelBindingsSlug("join");
    }

    protected function getUnionClauses() : string
    {
        if (! property_exists($this->query, "unions")
            || ! $this->query->unions
        ) {
            return "";
        }

        $unions = array_map(
            fn ($union) => (data_get($union, "all") ? "all_" : "")
                . sha1(
                    $union["query"]->toSql()
                    . $this->encodeForKeyHash($union["query"]->getBindings())
                ),
            $this->query->unions,
        );

        return "-union_" . implode("_", $unions);
    }

    // How many binding slots an In-family clause owns.
    //
    // whereIn() calls addBinding(cleanBindings($values)), so its count is the
    // value list with every Expression removed. whereIntegerInRaw() and
    // whereIntegerNotInRaw() inline their values into the SQL and call
    // addBinding() not at all, so they own nothing. Counting the value list
    // for those two pushed the cursor past bindings belonging to later
    // clauses, and those clauses then read another query's values.
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
        $values = implode("_", array_map(function ($value) {
            return $this->escapeKeySegment($this->processEnum($value));
        }, $where["values"]));

        // whereRowValues() binds through cleanBindings() too, so an Expression
        // among the values is inlined into the SQL and owns no binding slot.
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
            $columns = array_map(function ($column) {
                return $this->expressionToString($column);
            }, $this->query->columns);

            return "_" . implode("_", $columns);
        }

        $columns = array_map(function ($column) {
            return $this->expressionToString($column);
        }, $columns);

        return "_" . implode("_", $columns);
    }

    protected function getRawClauses(array $where) : string
    {
        if (! in_array($where["type"], ["raw"])) {
            return "";
        }

        // Substitute each binding back into the raw SQL in place of its "?",
        // so the clause reads as the statement that will actually run.
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

    // A clause names itself by its type, and adds its comparison operator when
    // it carries one. The single exception is "Basic" — the plain
    // where("column", "=", $value) shape — which names itself by its operator
    // alone. That exception is what keeps every existing plain-where key
    // byte-identical; everything else needs its type, because a type is the
    // only thing telling two clauses apart when column, operator and value all
    // match. whereDate() and where() both compile to "= ?" against the same
    // column, as do whereDay(5) and whereMonth(5), whose bindings are both the
    // zero-padded "05".
    //
    // This replaces a hard-coded list of type names. That list is what made the
    // key wrong twice over: it did not name Date, Day, Month, Year, Time, Sub,
    // JsonLength or JsonBoolean, so each fell through to its operator and lost
    // its identity; and it did not name betweenColumns, valueBetween, Like or
    // JsonOverlaps, which carry no operator at all, so each read a key that is
    // not there and raised "Undefined array key". Stating the rule instead of
    // enumerating its members is what stops the next type Laravel adds from
    // landing in one of those two holes.
    protected function getTypeClause($where) : string
    {
        $segments = $where["type"] === "Basic"
            ? []
            : [strtolower($where["type"])];

        if (data_get($where, "operator") !== null) {
            $segments[] = strtolower($where["operator"]);
        }

        // A "Basic" clause always carries an operator, so this is only reached
        // if something outside Laravel pushes a where array that does not.
        // Naming it by its type is worse than nothing but better than an empty
        // segment, which would collapse it onto every other such clause.
        if ($segments === []) {
            $segments[] = strtolower($where["type"]);
        }

        // whereNotBetween(), whereJsonDoesntContain() and their siblings reuse
        // the affirmative clause's type and negate it with a separate "not"
        // flag, so without this they share a cache key with the query that
        // returns exactly the rows they exclude.
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

    // $escape encodes each value individually, before they are joined on "_".
    // It is off by default because most callers hand the result straight to
    // getValuesFromBindings(), which discards it and escapes the binding
    // instead; getInAndNotInClauses() is the caller that keeps it.
    protected function getValuesFromWhere(array $where, bool $escape = false) : string
    {
        if (array_key_exists("value", $where)
            && is_object($where["value"])
            && get_class($where["value"]) === "DateTime"
        ) {
            return $where["value"]->format("Y-m-d-H-i-s");
        }

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

        // where("column", ">", new Expression(...)) is compiled straight into
        // the SQL and binds nothing, so consuming a slot for it would hand the
        // next clause a value that is not its own.
        if (data_get($where, "value") instanceof Expression) {
            return $values;
        }

        $this->currentBinding++;
        $values = $this->stringifyBinding($currentBinding);

        // whereBetween() binds both bounds through cleanBindings(), so an
        // Expression bound leaves the clause owning one slot rather than two.
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

    // A null $wheres means "the caller named no clause list", so the query's
    // own clauses are used. An empty array means "this clause list is
    // genuinely empty", and nothing is walked.
    //
    // Collapsing the two is what made getNestedClauses() recurse without end.
    // whereExists() over a subquery carrying no where clause handed [] down,
    // this method read that as "use $this->query->wheres", and those still
    // hold the Exists clause that called it. The recursion has no floor, so it
    // exhausts the stack. PHP raises no error for that: the process dies on
    // SIGSEGV, which no handler can catch.
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

        if (! method_exists($this->model, $related)) {
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
        $result = "";

        foreach ($items as $value) {
            if (is_string($value)) {
                $value = str_replace('"', '', $value);
                $value = explode(" ", $value);

                if (count($value) === 1) {
                    $value = $value[0];
                }
            }

            if (is_array($value)) {
                $result .= $this->recursiveImplode($value, $glue);

                continue;
            }

            $result .= $glue . $value;
        }

        return $result;
    }

    // A bound value is the one key segment an application controls outright, so
    // it is the one that has to be escaped. Values reached through
    // getInAndNotInClauses() are deliberately left alone: that path detects a
    // raw 16-byte binary UUID by its length, which escaping would change.
    private function stringifyBinding(mixed $binding) : string
    {
        if (is_object($binding)
            && get_class($binding) === "DateTime"
        ) {
            $binding = $binding->format("Y-m-d-H-i-s");
        }

        return $this->escapeKeySegment((string) $binding);
    }

    private function escapeKeySegment(string $segment) : string
    {
        return strtr($segment, self::KEY_SEPARATOR_ESCAPES);
    }

    // The backstop. Every builder property that no named method reads and that
    // is not connection plumbing lands in one hash, so the key changes when it
    // does. Today that covers aggregate, indexHint, groupLimit, lock,
    // unionLimit, unionOffset, unionOrders and afterQueryCallbacks. Tomorrow it
    // covers whatever Laravel adds, with no edit here.
    //
    // Only non-empty values are hashed, so a builder in its default state
    // produces nothing and every existing key is unchanged.
    private function getUnkeyedQueryPropertiesSlug() : string
    {
        $unkeyed = Collection::make(get_object_vars($this->query))
            ->except(array_merge(
                self::KEYED_QUERY_PROPERTIES,
                self::UNKEYED_INFRASTRUCTURE_PROPERTIES,
            ))
            ->reject(fn ($value) => $value === null || $value === false || $value === [])
            ->map($this->normalizeForHash(...))
            ->sortKeys()
            ->all();

        if (! $unkeyed) {
            return "";
        }

        return "-q" . substr(sha1($this->encodeForKeyHash($unkeyed)), 0, 12);
    }

    // json_encode() renders an Expression as "{}" because its value is
    // protected, and serialize() throws on a Closure or on anything holding a
    // PDO connection. Both would make a hash that cannot tell two queries
    // apart, or one that fatals. Reducing every object to something printable
    // first keeps the hash total and honest about what it can distinguish.
    private function normalizeForHash(mixed $value) : mixed
    {
        if (is_array($value)) {
            return array_map($this->normalizeForHash(...), $value);
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

    private function processEnum(
        BackedEnum|UnitEnum|Expression|DateTimeInterface|int|float|bool|string|null $value,
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        } elseif ($value instanceof UnitEnum) {
            return $value->name;
        } elseif ($value instanceof Expression) {
            return $this->expressionToString($value);
        } elseif ($value instanceof DateTimeInterface) {
            return $value->format("Y-m-d-H-i-s");
        }

        return "{$value}";
    }

    private function processEnums(array $values, bool $escape = false): array
    {
        return array_map(
            fn($value) => $escape
                ? $this->escapeKeySegment($this->processEnum($value))
                : $this->processEnum($value),
            $values,
        );
    }

    private function expressionToString(Expression|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) $value->getValue($this->query->getConnection()->getQueryGrammar());
    }
}
