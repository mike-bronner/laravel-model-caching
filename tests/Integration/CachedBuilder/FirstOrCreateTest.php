<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;

test('first or create flushes cache for model', function () {
    (new Author)->truncate();

    $items = [
        ['name' => 'foo', 'email' => 'test1@noemail.com'],
        ['name' => 'foo', 'email' => 'test2@noemail.com'],
        ['name' => 'foo', 'email' => 'test3@noemail.com'],
        ['name' => 'foo', 'email' => 'test4@noemail.com'],
        ['name' => 'foo', 'email' => 'test5@noemail.com'],
    ];

    foreach ($items as $item) {
        (new Author)->firstOrCreate($item);
    }

    $authors = (new Author)->get();

    expect($authors->count())->toEqual(5);
});
