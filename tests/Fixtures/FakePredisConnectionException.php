<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Predis\Connection\ConnectionException;

// Predis 3's ConnectionException constructor requires a NodeConnectionInterface,
// which is awkward to build in tests. This subclass keeps the simple string
// constructor (mirroring FakeDynamoDbConnectionException) while remaining a real
// Predis\Connection\ConnectionException so the package's connection-exception
// detection recognizes it.
class FakePredisConnectionException extends ConnectionException
{
    public function __construct(string $message = 'Connection refused')
    {
        $this->message = $message;
    }
}
