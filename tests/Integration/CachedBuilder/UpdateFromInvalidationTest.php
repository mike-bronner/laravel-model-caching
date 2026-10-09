<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\RunsOnPostgres;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, RunsOnPostgres::class);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    Author::factory()->count(2)->create();
    Publisher::factory()->create();
    Book::factory()->count(3)->create();
});

test('update from invalidates cache', function () {
    $before = (new Book)->orderBy("id")->pluck("title");

    Book::query()
        ->join("authors", "authors.id", "=", "books.author_id")
        ->where("authors.id", 1)
        ->updateFrom(["title" => "updated from"]);

    $after = (new Book)->orderBy("id")->pluck("title");
    $live = (new UncachedBook)->orderBy("id")->pluck("title");

    expect($live)->not->toEqual($before, "The write must change what the database returns");
    expect($after)->toEqual($live);
});
