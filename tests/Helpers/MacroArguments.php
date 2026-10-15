<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

function authorsThroughLocalMacro(string $innerMethod, mixed $argument): Builder
{
    $query = AuthorWithFilteringBuilder::query();
    $query->macro("throughInnerBuilder", fn (Builder $builder, $argument) => tap(
        $builder,
        fn () => $builder->getInnerBuilder()->{$innerMethod}($argument),
    ));

    return $query->throughInnerBuilder($argument);
}

function authorsThroughGlobalMacro(string $innerMethod, mixed $argument): Builder
{
    return withGlobalEloquentMacro(
        "throughInnerBuilder",
        fn ($argument) => tap($this, fn () => $this->getInnerBuilder()->{$innerMethod}($argument)),
        fn () => AuthorWithFilteringBuilder::query()->throughInnerBuilder($argument),
    );
}

function bookAuthorRelationOver(Builder $authors, int $authorId = 1): BelongsTo
{
    return new BelongsTo($authors, (new UncachedBook)->forceFill(["author_id" => $authorId]), "author_id", "id", "author");
}
