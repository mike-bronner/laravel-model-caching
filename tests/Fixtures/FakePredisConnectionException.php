<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Predis\Connection\ConnectionException;

class FakePredisConnectionException extends ConnectionException
{
    public function __construct(string $message = 'Connection refused')
    {
        $this->message = $message;
    }
}
