<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('write does not flush while caching is disabled', function () {
    $before = cachedTitles();
    $query = (new Book)->newQuery()->where("id", 1);

    app("model-cache")->runDisabled(function () use ($query) {
        $query->update(["title" => "written while disabled"]);
    });

    expect(liveTitles())->not->toEqual($before);
    expect(cachedTitles())->toEqual($before);
});

test('write through disabled cache query still flushes', function () {
    cachedTitles();

    (new Book)->disableCache()->where("id", 1)->update(["title" => "written uncached"]);

    expect(cachedTitles())->toEqual(liveTitles());
});

test('write through locked query still flushes', function () {
    cachedTitles();

    (new Book)->lockForUpdate()->where("id", 1)->update(["title" => "written under lock"]);

    expect(cachedTitles())->toEqual(liveTitles());
});

function cachedTitles()
{
    return (new Book)->orderBy("id")->pluck("title");
}

function liveTitles()
{
    return (new UncachedBook)->orderBy("id")->pluck("title");
}
