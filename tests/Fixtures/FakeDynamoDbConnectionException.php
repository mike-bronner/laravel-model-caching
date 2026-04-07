<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Aws\DynamoDb\Exception\DynamoDbException;

class FakeDynamoDbConnectionException extends DynamoDbException
{
    public function __construct(string $message = 'Connection refused')
    {
        $this->message = $message;
    }

    public function isConnectionError(): bool
    {
        return true;
    }
}
