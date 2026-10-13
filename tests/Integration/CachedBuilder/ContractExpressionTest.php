<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ContractOnlyExpression;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

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
