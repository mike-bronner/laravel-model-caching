<?php

use GeneaLabs\LaravelModelCaching\ModelCaching;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

test('use eloquent builder is deprecated and still records the builder', function () {
    $deprecations = [];
    set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
        $deprecations[] = [$level, $message];

        return true;
    }, E_USER_DEPRECATED);

    try {
        ModelCaching::useEloquentBuilder(AuthorQueryBuilder::class);
        $builder = ModelCaching::builder();
        ModelCaching::useEloquentBuilder(Builder::class);
    } finally {
        restore_error_handler();
    }

    expect($builder)->toBe(AuthorQueryBuilder::class)
        ->and(ModelCaching::builder())->toBe(Builder::class)
        ->and($deprecations)->toHaveCount(2)
        ->and($deprecations[0][0])->toBe(E_USER_DEPRECATED)
        ->and($deprecations[0][1])->toContain('ModelCaching::useEloquentBuilder() is deprecated');
});
