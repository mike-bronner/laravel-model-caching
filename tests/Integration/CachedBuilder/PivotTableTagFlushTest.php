<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithStorePrefixedPivot;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PostWithUncachedTags;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedBookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StoreWithCoolingBooks;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

dataset("pivot writes", [
    "attach" => [
        fn (BelongsToMany $relation, array $case) => $relation
            ->attach(data_get($case, "unattached")),
    ],
    "detach" => [
        fn (BelongsToMany $relation, array $case) => $relation
            ->detach(data_get($case, "attached")),
    ],
    "sync" => [
        fn (BelongsToMany $relation, array $case) => $relation
            ->sync([data_get($case, "unattached")]),
    ],
    "updateExistingPivot" => [
        fn (BelongsToMany $relation, array $case) => $relation
            ->updateExistingPivot(data_get($case, "attached"), ["updated_at" => now()]),
    ],
]);

dataset("pivot relations", [
    "belongs to many, uncachable related, non-default pivot table" => [function (): array {
        [$bookId, $attached, $unattached] = bookStoreRowIds();

        return [
            "parent" => (new BookWithUncachedStore)->find($bookId),
            "relation" => "uncachedStores",
            "readTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesuncachedstore"),
                tag("stores"),
                tag("book-store"),
            ],
            "pivotTags" => [tag("book-store")],
            "keptTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesuncachedstore"),
                tag("stores"),
            ],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
    "morph to many, uncachable related" => [function (): array {
        [$postId, $attached, $unattached] = postTagRowIds();

        return [
            "parent" => (new PostWithUncachedTags)->find($postId),
            "relation" => "uncachedTags",
            "readTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesuncachedtag"),
                tag("tags"),
                tag("taggables"),
            ],
            "pivotTags" => [tag("taggables")],
            "keptTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesuncachedtag"),
                tag("tags"),
            ],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
    "belongs to many, uncachable related, database keying off" => [function (): array {
        config(["laravel-model-caching.use-database-keying" => false]);
        [$bookId, $attached, $unattached] = bookStoreRowIds();

        return [
            "parent" => (new BookWithUncachedStore)->find($bookId),
            "relation" => "uncachedStores",
            "readTags" => [
                "genealabs:laravel-model-caching:"
                    . "genealabslaravelmodelcachingtestsfixturesuncachedstore",
                "genealabs:laravel-model-caching:stores",
                "genealabs:laravel-model-caching:book-store",
            ],
            "pivotTags" => ["genealabs:laravel-model-caching:book-store"],
            "keptTags" => ["genealabs:laravel-model-caching:stores"],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
    "belongs to many, uncachable related, global cache prefix" => [function (): array {
        config(["laravel-model-caching.cache-prefix" => "global-prefix:"]);
        [$bookId, $attached, $unattached] = bookStoreRowIds();

        return [
            "parent" => (new BookWithUncachedStore)->find($bookId),
            "relation" => "uncachedStores",
            "readTags" => [
                tag("global-prefix:genealabslaravelmodelcachingtestsfixturesuncachedstore"),
                tag("global-prefix:stores"),
                tag("global-prefix:book-store"),
            ],
            "pivotTags" => [tag("global-prefix:book-store")],
            "keptTags" => [tag("global-prefix:stores")],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
    "belongs to many, pivot class with its own prefix" => [function (): array {
        [$bookId, $attached, $unattached] = bookStoreRowIds();

        return [
            "parent" => (new BookWithStorePrefixedPivot)->find($bookId),
            "relation" => "stores",
            "readTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesstore"),
                tag("stores"),
                tag("book-store"),
                tag("store-prefix:book-store"),
            ],
            "pivotTags" => [tag("book-store"), tag("store-prefix:book-store")],
            "keptTags" => [],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
    "belongs to many, parent with its own prefix" => [function (): array {
        [$storeId, $attached, $unattached] = storeBookRowIds();

        return [
            "parent" => (new PrefixedStore)->find($storeId),
            "relation" => "books",
            "readTags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbook"),
                tag("books"),
                tag("book-store"),
                tag("store-prefix:book-store"),
            ],
            "pivotTags" => [tag("book-store"), tag("store-prefix:book-store")],
            "keptTags" => [],
            "attached" => $attached,
            "unattached" => $unattached,
        ];
    }],
]);

test("a pivot write with model events off flushes the pivot table tag of a cached read", function (
    Closure $write,
    Closure $relationCase,
): void {
    expectPivotWriteToFlushRead($write, $relationCase());
})->with("pivot writes")
    ->with("pivot relations");

test("one pivot write flushes the parent, related and pivot tags together", function (): void {
    [$bookId, , $unattached] = bookStoreRowIds();
    $book = (new Book)->find($bookId);
    $sentinels = plantTaggedSentinels([
        tag("genealabslaravelmodelcachingtestsfixturesbook"),
        tag("books"),
        tag("genealabslaravelmodelcachingtestsfixturesstore"),
        tag("stores"),
        tag("book-store"),
    ]);

    $relation = $book->stores();

    writeWithoutModelEvents(fn () => $relation->attach($unattached));

    expect(survivingSentinels($sentinels))->toBe([]);
});

test("sync flushes the pivot tag exactly once", function (): void {
    [$bookId, , $unattached] = bookStoreRowIds();
    $relation = (new BookWithUncachedStore)->find($bookId)
        ->uncachedStores();
    $calls = spyOnRelationInvalidations($relation);

    writeWithoutModelEvents(fn () => $relation->sync([$unattached]));

    $pivotFlushes = collect($calls->getArrayCopy())
        ->filter(fn (array $tags): bool => in_array(tag("book-store"), $tags, true));

    expect($pivotFlushes)->toHaveCount(1);
});

test("a pivot write after caching is disabled leaves the pivot tag alone", function (): void {
    [$bookId, , $unattached] = bookStoreRowIds();
    $relation = (new BookWithUncachedStore)->find($bookId)
        ->uncachedStores();
    $sentinels = plantTaggedSentinels([tag("book-store")]);
    config(["laravel-model-caching.enabled" => false]);

    writeWithoutModelEvents(fn () => $relation->attach($unattached));

    expect(survivingSentinels($sentinels))->toBe([tag("book-store")]);
});

test("a pivot write survives a lost cache connection in the pivot tag flush", function (): void {
    config(["laravel-model-caching.fallback-to-database" => true]);
    [$bookId, , $unattached] = bookStoreRowIds();
    $relation = (new BookWithUncachedStore)->find($bookId)
        ->uncachedStores();
    $context = "pivot table cache flush";
    breakCacheConnection();
    Log::shouldReceive("warning")
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, $context));
    Log::shouldReceive("warning")
        ->withArgs(fn (string $message): bool => ! str_contains($message, $context));

    writeWithoutModelEvents(fn () => $relation->attach($unattached));

    $attached = DB::table("book_store")
        ->where(["book_id" => $bookId, "store_id" => $unattached])
        ->exists();

    expect($attached)->toBeTrue();
});

test("a pivot write keeps the pivot tag during the parent's cool-down", function (): void {
    [$bookId, , $unattached] = bookStoreRowIds();
    (new BookWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    $relation = (new BookWithCooldown)->find($bookId)
        ->stores();
    $sentinels = plantTaggedSentinels([tag("book-store")]);

    writeWithoutModelEvents(fn () => $relation->attach($unattached));

    expect(survivingSentinels($sentinels))->toBe([tag("book-store")]);
});

test("a pivot write keeps the pivot tag during the related model's cool-down", function (): void {
    [$storeId, , $unattached] = storeBookRowIds();
    (new PrefixedBookWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    $relation = (new StoreWithCoolingBooks)->find($storeId)
        ->books();
    $sentinels = plantTaggedSentinels([tag("book-store")]);

    writeWithoutModelEvents(fn () => $relation->attach($unattached));

    expect(survivingSentinels($sentinels))->toBe([tag("book-store")]);
});
