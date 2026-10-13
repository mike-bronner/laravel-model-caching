<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('pagination provides different links on different pages', function () {
    $book = (new Book)
        ->take(11)
        ->get()
        ->last();
    $page1 = $this->get("pagination-test");

    $page1->assertSee('aria-current="page"', false);
    $page2 = $this->get("pagination-test?page=2");
    $page2->assertSee('aria-current="page"', false);
    $page2->assertSee($book->title, false);
});

test('advanced pagination', function () {
    $response = $this->get("pagination-test?page[size]=1");

    $response->assertSee('aria-current="page"', false);
});

test('custom pagination', function () {
    $response = $this->get("pagination-test2?custom-page=2");

    $response->assertSee('aria-current="page"', false);
});
