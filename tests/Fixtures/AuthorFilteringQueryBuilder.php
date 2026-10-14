<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Support\Arr;

class AuthorFilteringQueryBuilder extends AuthorQueryBuilder
{
    protected array $resultFilters = [];

    public function whereNameIn(array $names): static
    {
        return $this->whereIn("name", $names);
    }

    public function whereFamousStatus(IntegerStatus $status): static
    {
        return $this->where("is_famous", $status->value);
    }

    public function orderedBy(AuthorOrder $order): static
    {
        return $this->orderBy("id", $order === AuthorOrder::Newest ? "desc" : "asc");
    }

    public function orderedByFirstOf(array $orders): static
    {
        return $this->orderedBy($orders[0]);
    }

    public function ignoring(array $values): static
    {
        return $this;
    }

    public function keepResultsWhere(callable|array $filters): static
    {
        $this->resultFilters = Arr::flatten(Arr::wrap($filters));

        return $this;
    }

    public function get($columns = ["*"])
    {
        return collect($this->resultFilters)
            ->reduce(
                fn ($models, callable $filter) => $models->filter($filter)->values(),
                parent::get($columns),
            );
    }

    public function applyCallback(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        return $this->toBase()->updateOrInsert($attributes, $values);
    }
}
