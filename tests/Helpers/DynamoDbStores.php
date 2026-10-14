<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use Illuminate\Cache\Repository;

function useInMemoryDynamoDbStore(
    string $driver = 'dynamodb',
    string $storeClass = FakeDynamoDbStore::class,
): void {
    $storeClass::reset();

    app('cache')->extend($driver, function () use ($storeClass) {
        return new Repository(new $storeClass);
    });

    app('cache')->forgetDriver('dynamodb-model');

    config([
        'cache.stores.dynamodb-model' => ['driver' => $driver],
        'laravel-model-caching.store' => 'dynamodb-model',
    ]);
}

function invokeProtectedMethod(
    object $target,
    string $method,
    array $arguments = [],
): mixed {
    $reflectionMethod = new ReflectionMethod($target, $method);

    return $reflectionMethod->invokeArgs($target, $arguments);
}

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
