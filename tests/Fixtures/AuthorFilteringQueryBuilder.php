<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class AuthorFilteringQueryBuilder extends AuthorQueryBuilder
{
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
