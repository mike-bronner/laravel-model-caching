<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('decrementing invalidates cache', function () {
    $book = (new Book)
        ->find(1);
    $originalPrice = $book->price;
    $originalDescription = $book->description;

    $book->decrement("price", 1.25, ["description" => "test description update"]);
    $book = (new Book)
        ->find(1);

    expect($book->price)->toEqual($originalPrice - 1.25);
    expect($book->description)->not->toEqual($originalDescription);
    expect("test description update")->toEqual($book->description);
});
