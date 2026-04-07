<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Aws\DynamoDb\Exception\DynamoDbException;

class FakeDynamoDbNonConnectionException extends DynamoDbException
{
    public function __construct(string $message = 'Something else')
    {
        $this->message = $message;
    }

    public function isConnectionError(): bool
    {
        return false;
    }
}
