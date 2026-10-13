<?php

declare(strict_types=1);

use Aws\DynamoDb\DynamoDbClient;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedSupplier;

beforeEach(function () {
    if (! class_exists(DynamoDbClient::class)) {
        $this->markTestSkipped('aws/aws-sdk-php is required for real DynamoDB smoke tests.');
    }

    $endpoint = env('DYNAMODB_ENDPOINT');
    $this->tableName = env('DYNAMODB_CACHE_TABLE', 'cache');

    if (! $endpoint) {
        $this->markTestSkipped('Set DYNAMODB_ENDPOINT to run real DynamoDB smoke tests.');
    }

    $this->dynamoDbClient = new DynamoDbClient([
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'endpoint' => $endpoint,
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', 'testing'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'testing'),
        ],
    ]);

    config([
        'cache.stores.dynamodb-real-model' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID', 'testing'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'testing'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => $this->tableName,
            'endpoint' => $endpoint,
            'attributes' => [
                'key' => 'key',
                'value' => 'value',
                'expiration' => 'expires_at',
            ],
        ],
        'laravel-model-caching.store' => 'dynamodb-real-model',
    ]);

    app('cache')->forgetDriver('dynamodb-real-model');

    purgeTable();
});

test('real dynamo db supports all queries and logical invalidation', function () {
    $cachedAuthors = Author::all();
    $authorId = $cachedAuthors->first()->id;

    expect($cachedAuthors)->not->toBeEmpty();
    expect(Author::all())->toEqual($cachedAuthors);
    expect(dynamoDbKeys())->not->toBeEmpty();

    UncachedAuthor::query()
        ->where('id', $authorId)
        ->update(['name' => 'REAL_DYNAMODB_UPDATED_AUTHOR']);

    $this->artisan('modelCache:clear')
        ->assertExitCode(0);

    $freshAuthors = Author::all();

    expect($freshAuthors->firstWhere('id', $authorId)->name)->toBe('REAL_DYNAMODB_UPDATED_AUTHOR');
});

test('real dynamo db serializes through relations', function () {
    $eagerLoadedPrinters = Author::with('printers')
        ->first()
        ->printers;
    $liveEagerLoadedPrinters = UncachedAuthor::with('printers')
        ->first()
        ->printers;
    $lazyLoadedHistory = Supplier::first()
        ->history;
    $liveLazyLoadedHistory = UncachedSupplier::first()
        ->history;

    expect($eagerLoadedPrinters->pluck('id')->toArray())->toEqual(
        $liveEagerLoadedPrinters->pluck('id')->toArray(),
    );
    expect($lazyLoadedHistory->id)->toBe($liveLazyLoadedHistory->id);
});

test('real dynamo db keeps cooldown keys unversioned', function () {
    AuthorWithCooldown::query()
        ->withCacheCooldownSeconds(60)
        ->get();

    $keys = dynamoDbKeys();

    expect(collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:seconds')))->toBeTrue();
    expect(collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:invalidated-at')))->toBeTrue();
    expect(collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:seconds:versions:')))->toBeFalse();
});

function purgeTable(): void
{
    foreach (scanTable() as $item) {
        test()->dynamoDbClient->deleteItem([
            'TableName' => test()->tableName,
            'Key' => [
                'key' => ['S' => $item['key']['S']],
            ],
        ]);
    }
}

function dynamoDbKeys(): array
{
    return array_map(
        fn (array $item) => $item['key']['S'],
        scanTable(),
    );
}

function scanTable(): array
{
    $items = [];
    $exclusiveStartKey = null;

    do {
        $scanParameters = [
            'TableName' => test()->tableName,
        ];

        if ($exclusiveStartKey) {
            $scanParameters['ExclusiveStartKey'] = $exclusiveStartKey;
        }

        $response = test()->dynamoDbClient->scan($scanParameters);
        $items = array_merge($items, $response['Items'] ?? []);
        $exclusiveStartKey = $response['LastEvaluatedKey'] ?? null;
    } while ($exclusiveStartKey);

    return $items;
}
