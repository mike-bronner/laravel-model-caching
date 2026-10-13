<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Support\Collection;

test('calling all then first queries returns differing results', function () {
    $allAuthors = (new Author)->all();
    $firstAuthor = (new Author)->first();

    expect($firstAuthor)->not->toEqual($allAuthors);
    expect($firstAuthor)->toBeInstanceOf(Author::class);
    expect($allAuthors)->toBeInstanceOf(Collection::class);
});

test('calling get then first queries returns differing results', function () {
    $allAuthors = (new Author)->get();
    $firstAuthor = (new Author)->first();

    expect($firstAuthor)->not->toEqual($allAuthors);
    expect($firstAuthor)->toBeInstanceOf(Author::class);
    expect($allAuthors)->toBeInstanceOf(Collection::class);
});

test('using destroy invalidates cache', function () {
    $allAuthors = (new Author)->get();
    $firstAuthor = $allAuthors->first();
    (new Author)->destroy($firstAuthor->id);
    $updatedAuthors = (new Author)->get()->keyBy("id");

    expect($updatedAuthors)->not->toEqual($allAuthors);
    expect($allAuthors->contains($firstAuthor))->toBeTrue();
    expect($updatedAuthors->contains($firstAuthor))->toBeFalse();
});

test('all method cache gets invalidated', function () {
    $allAuthors = (new Author)->all();
    $firstAuthor = $allAuthors->first();
    $firstAuthor->delete();
    $updatedAuthors = (new Author)->all();

    expect($updatedAuthors)->not->toEqual($allAuthors);
    expect($allAuthors->contains($firstAuthor))->toBeTrue();
    expect($updatedAuthors->contains($firstAuthor))->toBeFalse();
});
