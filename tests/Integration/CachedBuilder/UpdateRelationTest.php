<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('in random order caches results', function () {
    $book = (new Book)
        ->with("stores")
        ->whereHas("stores")
        ->first();

    // Count actual stores for this book before the update (may be >1 due to random seeding).
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

    // All stores belonging to the book should have been updated.
    expect($updatedCount)->toEqual($storeCount);
});
