<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use GeneaLabs\LaravelModelCaching\Traits\Buildable;
use GeneaLabs\LaravelModelCaching\Traits\BuilderCaching;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;

class CachedBuilder extends Builder
{
    use Buildable;
    use BuilderCaching;
    use Caching;

    protected ?Builder $innerBuilder = null;
    private static ?ReflectionClass $builderReflection = null;

    public function setInnerBuilder(Builder $builder): static
    {
        $this->innerBuilder = $builder;

        return $this;
    }

    public function getInnerBuilder(): ?Builder
    {
        return $this->innerBuilder;
    }

    public function setModel(Model $model)
    {
        $this->innerBuilder?->setModel($model);

        return parent::setModel($model);
    }

    /**
     * Synchronise builder state from the outer CachedBuilder to the inner
     * (custom) builder before delegating a terminal operation.
     *
     * Note: wheres, orders, bindings, and other query-level state are NOT
     * synced here because both builders share the same underlying $query
     * (Illuminate\Database\Query\Builder) instance — it was passed to both
     * constructors. Only Eloquent-level state (eager loads, scopes, etc.)
     * needs explicit propagation.
     */
    protected function syncStateToInner(): void
    {
        if (! $this->innerBuilder) {
            return;
        }

        $this->innerBuilder->setEagerLoads($this->getEagerLoads());
        $this->innerBuilder->pendingAttributes = $this->pendingAttributes;

        if (! self::$builderReflection) {
            self::$builderReflection = new ReflectionClass(Builder::class);
        }

        // WARNING: These properties are internal to Illuminate\Database\Eloquent\Builder
        // and are not part of Laravel's public API. Verified against Laravel 10.x–12.x.
        // If Laravel renames or removes them, this will break. There are no public
        // accessors for `scopes` or `afterQueryCallbacks` as of Laravel 12.
        // `getRemovedScopes()` exists but returns values only (no setter).
        foreach (['scopes', 'removedScopes', 'afterQueryCallbacks'] as $prop) {
            $p = self::$builderReflection->getProperty($prop);
            $p->setValue($this->innerBuilder, $p->getValue($this));
        }
    }

    public function __clone()
    {
        if ($this->innerBuilder) {
            $this->innerBuilder = clone $this->innerBuilder;
        }

        parent::__clone();
    }

    /**
     * Record every table a relation named by string reads, then hand the
     * resolved relation back to Laravel unchanged.
     *
     * This is the single point at which Laravel turns a relation *name* into a
     * Relation, for has(), hasMorph(), whereMorphedTo(), whereNotMorphedTo()
     * and withAggregate() alike. Recording here therefore costs no extra call
     * to the relation method: the call being observed is the one Laravel was
     * already making.
     *
     * Recording matters because most of those constraints leave nothing on
     * `wheres` for CacheTags to walk. Only ">= 1" and "< 1" reach
     * addWhereExistsQuery(), which keeps its builder; every other operator and
     * count pair goes to addWhereCountQuery(), which compiles the subquery to
     * an Expression and drops the builder. withCount()/withExists() put their
     * subquery in `columns`, which CacheTags does not walk either. Without the
     * record, `has("books", ">", 1)` would name no table and a write to `books`
     * would not bust the cached query.
     *
     * No try/catch: parent::getRelationWithoutConstraints() runs first, so a
     * relation method that throws throws out of Laravel's own call, exactly as
     * it would without this override. Tag building never becomes the thing that
     * throws, and it never swallows a failure Laravel would have surfaced.
     */
    protected function getRelationWithoutConstraints($relation)
    {
        $resolved = parent::getRelationWithoutConstraints($relation);

        $this->recordRelatedSubqueryTables($resolved);

        return $resolved;
    }

    /**
     * Record every table a *dotted* relationship existence check reads, then
     * add the check as Laravel normally would.
     *
     * A single-segment name is left to parent::has(), which resolves it through
     * getRelationWithoutConstraints() above — one call to the relation method,
     * not two. A dotted chain cannot be: has() hands it to hasNested(), which
     * resolves each segment after the first against the *subquery's* builder —
     * the one being compiled away — so a record made there is written to a
     * builder nobody reads. The chain is therefore walked explicitly here, and
     * pays a second call to each relation method it walks.
     *
     * A Relation passed in already resolved is recorded here too, since it
     * never reaches the choke point, and recording it costs no call at all.
     */
    public function has($relation, $operator = ">=", $count = 1, $boolean = "and", ?Closure $callback = null)
    {
        if (! is_string($relation) || str_contains($relation, ".")) {
            $this->recordRelatedSubqueryTables($relation);
        }

        return parent::has($relation, $operator, $count, $boolean, $callback);
    }

    /**
     * Walk a relation — or a dotted chain of them — and record each related
     * table together with the model that owns it, so the tag carries that
     * model's own cache prefix rather than the querying model's.
     *
     * A MorphTo segment ends the walk: the concrete class depends on the row's
     * morph-type column, so neither the table nor the model is knowable
     * statically. A segment that is not a relation ends it too, rather than
     * guessing at a table for something Laravel is about to reject itself.
     *
     * Resolving a segment of a dotted chain calls the relation method, which
     * Laravel then calls again for the constraint itself. A relation method
     * that only returns $this->belongsTo(...) and friends is unaffected; one
     * with side effects sees them twice, the same trade
     * getDeferredJoinedSubqueryTables() makes in CacheTags. Single-segment
     * names do not pay this: they arrive already resolved, from
     * getRelationWithoutConstraints() above.
     *
     * method_exists() sees only declared methods, so a relation served by
     * __call() or by a macro records nothing and its table goes untagged. That
     * gap predates this walk and is not closed here: the alternative is calling
     * an arbitrary method to find out what it returns.
     */
    protected function recordRelatedSubqueryTables($relation): void
    {
        $query = $this->getQuery();

        if (! method_exists($query, "recordRelatedSubqueryTable")) {
            return;
        }

        if ($relation instanceof Relation) {
            $segments = [$relation];
        } elseif (is_string($relation)) {
            $segments = explode(".", $relation);
        } else {
            return;
        }

        $model = $this->getModel();

        foreach ($segments as $segment) {
            if (is_string($segment)) {
                if (! method_exists($model, $segment)) {
                    return;
                }

                $segment = Relation::noConstraints(
                    fn () => $model->{$segment}(),
                );
            }

            if (! $segment instanceof Relation || $segment instanceof MorphTo) {
                return;
            }

            $model = $segment->getRelated();
            $query->recordRelatedSubqueryTable($model->getTable(), $model);
        }
    }
}
