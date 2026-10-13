<?php

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotFlushRepository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Redis\RedisManager;

const REPOSITORY_TEST_TAG = 'model-cache-repository-test';

test('the default cache store is used when no store is configured', function () {
    config([
        'laravel-model-caching.store' => null,
        'cache.default' => 'array',
    ]);

    $repository = ModelCacheRepository::make();
    $repository->forever('default-store-entry', 'cached value', [REPOSITORY_TEST_TAG]);

    expect($repository->usesDynamoDb())->toBeFalse()
        ->and($repository->get('default-store-entry', [REPOSITORY_TEST_TAG]))->toBe('cached value')
        ->and(app('cache')->store('array')->tags([REPOSITORY_TEST_TAG])->get('default-store-entry'))
        ->toBe(ModelCacheRepository::SERIALIZED_VALUE_PREFIX . serialize('cached value'));
});

test('a cached string written without the serialization prefix is returned as stored', function () {
    app('cache')->store('model')->tags([REPOSITORY_TEST_TAG])->forever('raw-entry', 'raw value');
    $repository = ModelCacheRepository::make();
    $callbackRan = false;

    $remembered = $repository->rememberForever('raw-entry', [REPOSITORY_TEST_TAG], function () use (&$callbackRan) {
        $callbackRan = true;

        return 'fresh value';
    });

    expect($repository->get('raw-entry', [REPOSITORY_TEST_TAG]))->toBe('raw value')
        ->and($remembered)->toBe('raw value')
        ->and($callbackRan)->toBeFalse();
});

test('a cross slot flush failure on a non redis store is not swallowed', function () {
    $repository = new ModelCacheRepository(new CrossSlotFlushRepository(new ArrayStore), false);

    expect(fn () => $repository->invalidateTags([REPOSITORY_TEST_TAG]))
        ->toThrow(RuntimeException::class, 'non-Redis store');
});

test('a delete failure other than cross slot is not swallowed', function () {
    $realStore = app('cache')->store('model')->getStore();
    $realStore->forever('genealabs:laravel-model-caching:delete-failure', 'kept');
    $store = new class(app('redis'), $realStore->getPrefix(), 'model-cache') extends RedisStore
    {
        public function connection()
        {
            return new class(parent::connection())
            {
                public function __construct(private readonly object $connection) {}

                public function del($keys)
                {
                    throw new RedisException('LOADING Redis is loading the dataset in memory');
                }

                public function __call(string $name, array $arguments)
                {
                    return $this->connection->{$name}(...$arguments);
                }
            };
        }
    };
    $repository = new ModelCacheRepository(new Repository($store), false);

    expect(fn () => $repository->invalidateAll())
        ->toThrow(RedisException::class, 'LOADING')
        ->and($realStore->get('genealabs:laravel-model-caching:delete-failure'))->toBe('kept');
});

test('clearing the entire cache over predis deletes only package keys', function (string $clientPrefix) {
    $token = (int) env('TEST_TOKEN', 1);
    $store = new RedisStore(predisManager($clientPrefix), "lmc-test-{$token}:", 'model-cache-predis');
    $connection = $store->connection();
    $connection->flushdb();
    $store->tags([REPOSITORY_TEST_TAG])->forever('genealabs:laravel-model-caching:entry', 'cached');
    $store->forever('genealabs:laravel-model-caching:plain-entry', 'cached');
    $connection->set('foreign:keep-me', 'should-survive');

    try {
        (new ModelCacheRepository(new Repository($store), false))->invalidateAll();

        expect($store->tags([REPOSITORY_TEST_TAG])->get('genealabs:laravel-model-caching:entry'))->toBeNull()
            ->and($store->get('genealabs:laravel-model-caching:plain-entry'))->toBeNull()
            ->and($connection->get('foreign:keep-me'))->toBe('should-survive')
            ->and((string) ($connection->client()->getOptions()->prefix?->getPrefix() ?? ''))->toBe($clientPrefix);
    } finally {
        $connection->flushdb();
    }
})->with([
    'with a client prefix' => ['predis-tenant:'],
    'without a client prefix' => [''],
]);

function predisManager(string $clientPrefix): RedisManager
{
    $options = $clientPrefix === ''
        ? []
        : ['prefix' => $clientPrefix];

    return new RedisManager(app(), 'predis', [
        'options' => [],
        'model-cache-predis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 3,
            'options' => $options,
        ],
    ]);
}
