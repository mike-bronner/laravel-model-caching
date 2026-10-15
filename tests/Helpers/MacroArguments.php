<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;

function authorsThroughLocalMacro(string $innerMethod, mixed $argument, Builder|Relation|null $query = null): Builder|Relation
{
    $query ??= AuthorWithFilteringBuilder::query();
    $builder = $query instanceof Relation ? $query->getQuery() : $query;
    $builder->macro("throughInnerBuilder", fn (Builder $builder, $argument) => tap(
        $builder,
        fn () => $builder->getInnerBuilder()->{$innerMethod}($argument),
    ));

    return $query->throughInnerBuilder($argument);
}

function authorsThroughGlobalMacro(string $innerMethod, mixed $argument, Builder|Relation|null $query = null): Builder|Relation
{
    return withGlobalEloquentMacro(
        "throughInnerBuilder",
        fn ($argument) => tap($this, fn () => $this->getInnerBuilder()->{$innerMethod}($argument)),
        fn () => ($query ?? AuthorWithFilteringBuilder::query())->throughInnerBuilder($argument),
    );
}

function bookAuthorRelationOver(Builder $authors, int $authorId = 1): BelongsTo
{
    return new BelongsTo($authors, (new UncachedBook)->forceFill(["author_id" => $authorId]), "author_id", "id", "author");
}

function filteringAuthorsThroughBooks(int $authorId = 1): HasManyThrough
{
    return (new Author)->forceFill(["id" => $authorId])
        ->hasManyThrough(AuthorWithFilteringBuilder::class, Book::class, "author_id", "id", "id", "author_id");
}

function stringableHolding(string $value): Stringable
{
    return new class ($value) {
        public function __construct(protected string $value)
        {
        }

        public function __toString(): string
        {
            return $this->value;
        }
    };
}

function arrayNested(int $depth, mixed $leaf): array
{
    return $depth === 1
        ? [$leaf]
        : [arrayNested($depth - 1, $leaf)];
}
