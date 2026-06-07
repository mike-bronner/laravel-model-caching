<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

// Decorates a real Redis connection so a batched DEL spanning multiple keys
// raises CROSSSLOT — as a cluster would — while single-key DELs and every other
// command pass through to the genuine connection. This lets the no-model clear
// path (invalidateAll -> flushRedisStoreByPrefix -> deleteRedisKeys) exercise its
// slot-safe per-key fallback without an actual multi-node cluster.
class CrossSlotDeleteConnection
{
    public function __construct(
        private readonly object $connection
    ) {
    }

    public function del($keys)
    {
        if (is_array($keys) && count($keys) > 1) {
            throw new \RedisException("CROSSSLOT Keys in request don't hash to the same slot");
        }

        return $this->connection->del($keys);
    }

    public function client()
    {
        return $this->connection->client();
    }

    public function scan($cursor, $options = [])
    {
        return $this->connection->scan($cursor, $options);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->connection->{$name}(...$arguments);
    }
}
