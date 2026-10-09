<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('in random order caches results', function () {
    $book = (new Book)
        ->with("stores")
        ->whereHas("stores")
        ->first();
    $book->stores()
        ->updateExistingPivot(
            $book->stores->first()->id,
            ["test" => "value"]
        );
    $updatedCount = (new Book)
        ->with("stores")
        ->whereHas("stores")
        ->first()
        ->stores()
        ->wherePivot("test", "value")
        ->count();

    expect($updatedCount)->toEqual(1);
});
