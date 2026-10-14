<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\Model;

function startCooldown(): void
{
    (new BookWithCooldown)
        ->withCacheCooldownSeconds(60)
        ->get();
}

function cachedPrices(): array
{
    return (new BookWithCooldown)->orderBy("id")->pluck("price", "id")->all();
}

function livePrices(): array
{
    return (new UncachedBook)->orderBy("id")->pluck("price", "id")->all();
}

function assertWriteKeepsCacheDuringCooldown(callable $write): void
{
    startCooldown();
    $before = cachedPrices();

    $write();

    expect(livePrices())->not->toEqual($before, "The write must change what the database returns");
    expect(cachedPrices())->toEqual($before);
}

function startCooldownAndCacheBooks(): BookWithCooldown
{
    (new BookWithCooldown)->withCacheCooldownSeconds(60)->get();
    (new BookWithCooldown)->orderBy("id")->pluck("title");

    expect(booksTagEntrySets())->not->toBeEmpty();

    return (new BookWithCooldown)->newQuery()->findOrFail(1);
}

function newStoreId(): int
{
    return Store::factory()->create()->id;
}

function prefixedAuthorNames(Model $model): array
{
    return $model->orderBy("id")
        ->pluck("name", "id")
        ->all();
}

function prefixedBookTitles(Model $model): array
{
    return $model->orderBy("id")
        ->pluck("title", "id")
        ->all();
}

function startPrefixedAuthorCooldown(): array
{
    (new PrefixedAuthorWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();

    return prefixedAuthorNames(new PrefixedAuthorWithCooldown);
}
