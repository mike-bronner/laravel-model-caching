<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('first returns all attributes for model', function () {
    $author = (new Author)
        ->where("id", "=", 1)
        ->first();
    $uncachedAuthor = (new UncachedAuthor)
        ->where("id", "=", 1)
        ->first();

    expect($uncachedAuthor->id)->toEqual($author->id);
    expect($uncachedAuthor->created_at)->toEqual($author->created_at);
    expect($uncachedAuthor->updated_at)->toEqual($author->updated_at);
    expect($uncachedAuthor->email)->toEqual($author->email);
    expect($uncachedAuthor->name)->toEqual($author->name);
});

test('first is not the same as all', function () {
    $authors = (new Author)
        ->all();
    $author = (new Author)
        ->first();

    expect($author)->not->toEqual($authors);
});
