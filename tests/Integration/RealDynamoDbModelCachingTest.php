<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use Aws\DynamoDb\DynamoDbClient;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedSupplier;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class RealDynamoDbModelCachingTest extends IntegrationTestCase
{
    protected DynamoDbClient $dynamoDbClient;
    protected string $tableName;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->purgeTable();
    }

    public function test_real_dynamo_db_supports_all_queries_and_logical_invalidation(): void
    {
        $cachedAuthors = Author::all();
        $authorId = $cachedAuthors->first()->id;

        $this->assertNotEmpty($cachedAuthors);
        $this->assertEquals($cachedAuthors, Author::all());
        $this->assertNotEmpty($this->dynamoDbKeys());

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'REAL_DYNAMODB_UPDATED_AUTHOR']);

        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        $freshAuthors = Author::all();

        $this->assertSame(
            'REAL_DYNAMODB_UPDATED_AUTHOR',
            $freshAuthors->firstWhere('id', $authorId)->name,
        );
    }

    public function test_real_dynamo_db_serializes_through_relations(): void
    {
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

        $this->assertEquals(
            $liveEagerLoadedPrinters->pluck('id')->toArray(),
            $eagerLoadedPrinters->pluck('id')->toArray(),
        );
        $this->assertSame($liveLazyLoadedHistory->id, $lazyLoadedHistory->id);
    }

    public function test_real_dynamo_db_keeps_cooldown_keys_unversioned(): void
    {
        AuthorWithCooldown::query()
            ->withCacheCooldownSeconds(60)
            ->get();

        $keys = $this->dynamoDbKeys();

        $this->assertTrue(
            collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:seconds')),
        );
        $this->assertTrue(
            collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:invalidated-at')),
        );
        $this->assertFalse(
            collect($keys)->contains(fn (string $key) => str_contains($key, '-cooldown:seconds:versions:')),
        );
    }

    private function purgeTable(): void
    {
        foreach ($this->scanTable() as $item) {
            $this->dynamoDbClient->deleteItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'key' => ['S' => $item['key']['S']],
                ],
            ]);
        }
    }

    private function dynamoDbKeys(): array
    {
        return array_map(
            fn (array $item) => $item['key']['S'],
            $this->scanTable(),
        );
    }

    private function scanTable(): array
    {
        $items = [];
        $exclusiveStartKey = null;

        do {
            $scanParameters = [
                'TableName' => $this->tableName,
            ];

            if ($exclusiveStartKey) {
                $scanParameters['ExclusiveStartKey'] = $exclusiveStartKey;
            }

            $response = $this->dynamoDbClient->scan($scanParameters);
            $items = array_merge($items, $response['Items'] ?? []);
            $exclusiveStartKey = $response['LastEvaluatedKey'] ?? null;
        } while ($exclusiveStartKey);

        return $items;
    }
}
