<?php

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotDeleteRedisStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotFlushRepository;
use Illuminate\Cache\Repository;

const INVALIDATE_ALL_TAG = 'cross-slot-tag';

test('invalidate all recovers from cross slot delete failure', function () {
    $realStore = app('cache')->store('model')->getStore();

    (new Author)->all();
    (new Book)->all();

    expect(modelCacheKeys())->not->toBeEmpty();

    $store = new CrossSlotDeleteRedisStore(app('redis'), $realStore->getPrefix(), 'model-cache');
    $modelCacheRepository = new ModelCacheRepository(new Repository($store), false);

    $modelCacheRepository->invalidateAll();

    expect(modelCacheKeys())->toBe([]);
});

test('invalidate tags recovers from cross slot failure with client prefix', function () {
    $repository = app('cache')->store('model-prefixed');
    $store = $repository->getStore();
    app('redis')->connection('model-cache-prefixed')->flushdb();

    $repository->tags([INVALIDATE_ALL_TAG])->forever('entry-a', 'value-a');
    $repository->tags([INVALIDATE_ALL_TAG])->forever('entry-b', 'value-b');

    expect($repository->tags([INVALIDATE_ALL_TAG])->get('entry-a'))->toBe('value-a');

    $throwingRepository = new CrossSlotFlushRepository($store);
    $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

    $modelCacheRepository->invalidateTags([INVALIDATE_ALL_TAG]);

    expect($repository->tags([INVALIDATE_ALL_TAG])->get('entry-a'))->toBeNull();
    expect($repository->tags([INVALIDATE_ALL_TAG])->get('entry-b'))->toBeNull();

    app('redis')->connection('model-cache-prefixed')->flushdb();
});
