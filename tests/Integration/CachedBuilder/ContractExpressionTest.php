<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ContractOnlyExpression;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

/**
 * Regression for PR #590. A custom Expression that implements
 * Illuminate\Contracts\Database\Query\Expression but does NOT extend
 * the concrete Illuminate\Database\Query\Expression must still be
 * recognized by CacheKey's instanceof checks. Otherwise the object
 * falls through into string concatenation in getOtherClauses() and
 * triggers a fatal "Object of class … could not be converted to string".
 */
test('cache key handles contract only expression as where column', function () {
    $expression = new ContractOnlyExpression('id');

    $cached = (new Book)
        ->where($expression, '>', 0)
        ->get();

    $live = (new UncachedBook)
        ->where($expression, '>', 0)
        ->get();

    expect($cached)->not->toBeEmpty();
    expect($cached->diffKeys($live))->toBeEmpty();
});
