<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

test('belongs to many relation tags contain pivot table', function () {
    $tags = makeCacheTags((new Book)->first()->stores());

    assertTagsContainTable($tags, 'book_store');
    assertTagsContainTable($tags, 'stores');
});

test('morph to many relation tags contain pivot table', function () {
    $tags = makeCacheTags((new Post)->first()->tags());

    assertTagsContainTable($tags, 'taggables');
    assertTagsContainTable($tags, 'tags');
});

test('has one through relation still tags intermediate table', function () {
    $tags = makeCacheTags((new Supplier)->first()->history());

    assertTagsContainTable($tags, 'users');
});

test('plain builder join tags still resolve through make cache tags', function () {
    $tags = makeCacheTags(
        (new Author)
            ->select('authors.*', 'books.title')
            ->join('books', 'books.author_id', '=', 'authors.id')
    );

    assertTagsContainTable($tags, 'books');
    assertTagsContainTable($tags, 'authors');
});

test('cached belongs to many read is invalidated by pivot table write', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $primedCount = (new User)->find($userId)->roles()->count();
    expect($primedCount)->toBeGreaterThan(0);

    (new CachableRoleUser)
        ->where('user_id', $userId)
        ->first()
        ->delete();

    expect((new User)->find($userId)->roles()->count())->toBe(
        $primedCount - 1,
        'A cached belongsToMany read should be invalidated by a write to its pivot table.',
    );
});
