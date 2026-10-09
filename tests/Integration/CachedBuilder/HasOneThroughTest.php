<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedSupplier;

test('eagerloaded has one through', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:suppliers:genealabslaravelmodelcachingtestsfixturessupplier-testing:{$this->testingSqlitePath}testing.sqlite:history-limit_1");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturessupplier",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixtureshistory",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:suppliers",
    ];

    $history = (new Supplier)
        ->with("history")
        ->first()
        ->history;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ->first()
        ->history;
    $liveResults = (new UncachedSupplier)
        ->with("history")
        ->first()
        ->history;

    expect($history->id)->toEqual($liveResults->id);
    expect($cachedResults->id)->toEqual($liveResults->id);
    expect($history)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('lazyloaded has one through', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:histories:genealabslaravelmodelcachingtestsfixtureshistory-join_inner_users_1f027b356718-users.supplier_id_=_1-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixtureshistory",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:histories",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:users",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesuser",
    ];

    $history = (new Supplier)
        ->first()
        ->history;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedSupplier)
        ->first()
        ->history;

    expect($history->id)->toEqual($liveResults->id);
    expect($cachedResults->id)->toEqual($liveResults->id);
    expect($history)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
