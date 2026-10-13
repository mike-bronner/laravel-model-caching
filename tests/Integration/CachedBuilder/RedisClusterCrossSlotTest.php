<?php

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotFlushRepository;

const INVALIDATE_TAGS_TAG = 'cross-slot-tag';

test('invalidate tags recovers from cross slot failure', function () {
    $repository = app('cache')->store('model');
    $store = $repository->getStore();

    $repository->tags([INVALIDATE_TAGS_TAG])->forever('entry-a', 'value-a');
    $repository->tags([INVALIDATE_TAGS_TAG])->forever('entry-b', 'value-b');

    expect($repository->tags([INVALIDATE_TAGS_TAG])->get('entry-a'))->toBe('value-a');
    expect(modelCacheKeys())->not->toBeEmpty();

    $throwingRepository = new CrossSlotFlushRepository($store);
    $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

    $modelCacheRepository->invalidateTags([INVALIDATE_TAGS_TAG]);

    expect($repository->tags([INVALIDATE_TAGS_TAG])->get('entry-a'))->toBeNull();
    expect($repository->tags([INVALIDATE_TAGS_TAG])->get('entry-b'))->toBeNull();
    expect(modelCacheKeys())->toBe([]);
});

test('invalidate tags rethrows non cross slot failures', function () {
    $store = app('cache')->store('model')->getStore();
    $throwingRepository = new class($store) extends CrossSlotFlushRepository
    {
        public function tags($names)
        {
            $tagged = parent::tags($names);

            return new class($tagged->getStore(), $tagged->getTags()) extends \Illuminate\Cache\RedisTaggedCache
            {
                public function flush()
                {
                    throw new \RedisException('READONLY You can\'t write against a read only replica.');
                }
            };
        }
    };
    $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

    expect(fn () => $modelCacheRepository->invalidateTags([INVALIDATE_TAGS_TAG]))
        ->toThrow(\RedisException::class);
});
