<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns\InteractsWithInMemoryDynamoDbStore;
use Illuminate\Contracts\Cache\Store;

class FakeDynamoDbStore implements Store
{
    use InteractsWithInMemoryDynamoDbStore;
}
