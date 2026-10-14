<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\ConfiguresUnprefixedStore;

uses(ConfiguresUnprefixedStore::class);

test('clear keeps application entries under the same store prefix', function () {
    $store = app("cache")->store("model");
    $store->forever("application:settings", "kept");
    $store->tags(["application-tag"])->forever("application:tagged", "kept");

    assertStaleCacheIsCleared();

    expect($store->get("application:settings"))->toBe("kept");
    expect($store->tags(["application-tag"])->get("application:tagged"))->toBe("kept");
});

test('clear removes every package key', function () {
    (new Author)->with("books")->get();
    (new AuthorWithCooldown)->withCacheCooldownSeconds(30)->get();

    expect(packageKeys())->not->toBeEmpty();

    $this->artisan("modelCache:clear")->assertExitCode(0);

    expect(packageKeys())->toBe([]);
});

test('clear on unprefixed store keeps application keys', function () {
    config(["laravel-model-caching.store" => "model-unprefixed"]);
    $connection = app("redis")->connection("model-cache-unprefixed");
    $applicationKey = "application:unprefixed:" . uniqid();
    $connection->set($applicationKey, "kept");

    try {
        assertStaleCacheIsCleared();

        expect($connection->get($applicationKey))->toBe("kept");
    } finally {
        $connection->del($applicationKey);
        $this->artisan("modelCache:clear");
    }
});
