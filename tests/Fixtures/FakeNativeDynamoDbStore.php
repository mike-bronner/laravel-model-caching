<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns\InteractsWithInMemoryDynamoDbStore;
use Illuminate\Cache\DynamoDbStore;

class FakeNativeDynamoDbStore extends DynamoDbStore
{
    use InteractsWithInMemoryDynamoDbStore;

    public function __construct() {}
}
