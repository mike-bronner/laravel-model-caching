<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ThrowingCacheStore;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;

class_exists('Aws\DynamoDb\Exception\DynamoDbException') || eval(<<<'PHP'
namespace Aws\DynamoDb\Exception;

class DynamoDbException extends \RuntimeException
{
    protected bool $connectionError = true;

    public function isConnectionError(): bool
    {
        return $this->connectionError;
    }
}

class ConnectionException extends DynamoDbException
{
    protected bool $connectionError = true;
}

class NonConnectionException extends DynamoDbException
{
    protected bool $connectionError = false;
}
PHP);

use Aws\DynamoDb\Exception\ConnectionException as AwsDynamoDbConnectionException;
use Aws\DynamoDb\Exception\NonConnectionException as AwsDynamoDbNonConnectionException;

class DynamoDbCacheFallbackTest extends IntegrationTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        config([
            'cache.stores.dynamodb-model' => ['driver' => 'dynamodb'],
            'laravel-model-caching.store' => 'dynamodb-model',
        ]);
    }

    private function breakCacheConnection(string $exceptionClass): void
    {
        $throwingStore = new ThrowingCacheStore($exceptionClass);
        $throwingRepo = new Repository($throwingStore);

        $this->app->extend('cache', function ($cache) use ($throwingRepo) {
            return new class($this->app, $throwingRepo) extends CacheManager
            {
                public function __construct($app, private Repository $throwingRepo)
                {
                    parent::__construct($app);
                }

                public function store($name = null)
                {
                    return $this->throwingRepo;
                }

                public function driver($driver = null)
                {
                    return $this->throwingRepo;
                }
            };
        });
    }

    public function testDynamoDbConnectionFailuresFallBackToDatabaseWhenEnabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $authors = Author::all();

        $this->assertNotEmpty($authors);
    }

    public function testNonConnectionDynamoDbExceptionsAreNotSwallowed(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(AwsDynamoDbNonConnectionException::class);

        $this->expectException(AwsDynamoDbNonConnectionException::class);

        Author::all();
    }
}
