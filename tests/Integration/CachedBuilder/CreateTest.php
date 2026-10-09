<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;

test('first or create flushes cache for model', function () {
    (new Author)->truncate();
    $noAuthors = (new Author)->get();
    (new Author)->create([
        'name' => 'foo',
        'email' => 'test1@noemail.com',
    ]);
    $authors = (new Author)->get();

    expect($noAuthors->count())->toEqual(0);
    expect($authors->count())->toEqual(1);
});
