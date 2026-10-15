<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithInlineGlobalScope;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\SoftDeletingScope;

test('a through relation never shares an entry with a call that removes a global scope', function (string $related, array $hiddenAuthor, Closure $unscoped) {
    (new UncachedAuthor)->where("id", 1)->update($hiddenAuthor);
    $bookIds = array_fill(0, (new UncachedBook)->where("author_id", 1)->count(), 1);

    $scoped = authorsThroughBooksOf($related)->get();
    $unscopedAuthors = $unscoped(authorsThroughBooksOf($related))->get();

    expect($scoped)->toBeEmpty()
        ->and($unscopedAuthors->pluck("id")->all())->toBe($bookIds)
        ->and(modelCacheEntryKeys())->toHaveCount(2);
})->with([
    "soft deletes removed by withoutGlobalScopes" => [
        AuthorWithFilteringBuilder::class,
        ["deleted_at" => "2026-01-01 00:00:00"],
        fn ($relation) => $relation->withoutGlobalScopes(),
    ],
    "soft deletes removed by withoutGlobalScope" => [
        AuthorWithFilteringBuilder::class,
        ["deleted_at" => "2026-01-01 00:00:00"],
        fn ($relation) => $relation->withoutGlobalScope(SoftDeletingScope::class),
    ],
    "soft deletes removed by withTrashed" => [
        AuthorWithFilteringBuilder::class,
        ["deleted_at" => "2026-01-01 00:00:00"],
        fn ($relation) => $relation->withTrashed(),
    ],
    "a scope with a binding removed by withoutGlobalScopes" => [
        AuthorWithInlineGlobalScope::class,
        ["name" => "Zed"],
        fn ($relation) => $relation->withoutGlobalScopes(),
    ],
    "a scope with a binding removed by name" => [
        AuthorWithInlineGlobalScope::class,
        ["name" => "Zed"],
        fn ($relation) => $relation->withoutGlobalScope("inlineScope"),
    ],
]);

test('a through relation still serves a scoped call from the cache', function () {
    $first = authorsThroughBooksOf(AuthorWithFilteringBuilder::class)->get();

    expectServedFromCache(fn () => expect(authorsThroughBooksOf(AuthorWithFilteringBuilder::class)->get()->pluck("id")->all())
        ->toBe($first->pluck("id")->all()));
});
