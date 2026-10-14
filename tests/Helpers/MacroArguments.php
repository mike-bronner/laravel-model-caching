<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use Illuminate\Database\Eloquent\Builder;

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
