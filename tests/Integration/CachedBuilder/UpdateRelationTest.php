<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('in random order caches results', function () {
    $book = (new Book)
        ->with("stores")
        ->whereHas("stores")
        ->first();

    $storeCount = $book->stores()->count();

    $book->stores()
        ->update(["name" => "test store name change"]);

    $updatedCount = (new Book)
        ->with("stores")
        ->whereHas("stores")
        ->first()
        ->stores()
        ->where("name", "test store name change")
        ->count();

    expect($updatedCount)->toEqual($storeCount);
});
