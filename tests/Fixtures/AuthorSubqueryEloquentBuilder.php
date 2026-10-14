<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AuthorSubqueryEloquentBuilder extends Builder
{
    public function joinAuthoredBooks(): static
    {
        $authored = DB::table("books")
            ->select("author_id")
            ->groupBy("author_id");

        return $this->select("authors.*")
            ->joinSub($authored, "authored", "authored.author_id", "=", "authors.id");
    }

    public function fromAuthoredBooks(): static
    {
        $authors = DB::table("books")
            ->selectRaw("author_id as id, null as deleted_at")
            ->distinct();

        return $this->fromSub($authors, "authors");
    }

    public function whereAuthoredBooks(): static
    {
        $authorIds = DB::table("books")
            ->select("author_id");

        return $this->whereIn("authors.id", $authorIds);
    }

    public function joinAuthoredBooksByName(): static
    {
        $authored = DB::table("books")
            ->select("author_id")
            ->groupBy("author_id");

        return $this->select("authors.*")
            ->joinSub(
                query: $authored,
                as: "authored",
                first: "authored.author_id",
                operator: "=",
                second: "authors.id",
            );
    }

    public function fromAuthoredBooksByName(): static
    {
        $authors = DB::table("books")
            ->selectRaw("author_id as id, null as deleted_at")
            ->distinct();

        return $this->fromSub(query: $authors, as: "authors");
    }

    public function whereAuthoredBooksByName(): static
    {
        $authorIds = DB::table("books")
            ->select("author_id");

        return $this->whereIn(column: "authors.id", values: $authorIds);
    }

    public function crossJoinAuthoredBooks(): static
    {
        $authored = DB::table("books")
            ->select("author_id")
            ->groupBy("author_id");

        return $this->select("authors.*")
            ->crossJoinSub($authored, "authored")
            ->whereColumn("authored.author_id", "authors.id");
    }

    public function callNamedLikeASubqueryMethod(string $method, mixed ...$arguments): static
    {
        return $this->{$method}(...$arguments);
    }

    public function excludingTheFirstAuthorFromWhereIn(): static
    {
        $this->macro("whereIn", $this->whereInExcludingTheFirstAuthor());

        return $this;
    }

    protected function whereInExcludingTheFirstAuthor(): Closure
    {
        return function (Builder $builder, string $column, mixed $values): Builder {
            $builder->getQuery()
                ->whereIn($column, $values);

            return $builder->where("authors.id", "!=", 1);
        };
    }

    public function orWhereNotIn(string $column, mixed $values): static
    {
        $query = $this->query;
        $query->whereNotIn($column, $values, "or");

        return $this;
    }
}
