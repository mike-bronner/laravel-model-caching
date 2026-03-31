<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Contracts\Cache\Store;

class FakeDynamoDbStore implements Store
{
    protected static array $items = [];
    protected static int $flushCount = 0;
    protected static int $writeCount = 0;

    public static function reset(): void
    {
        static::$items = [];
        static::$flushCount = 0;
        static::$writeCount = 0;
    }

    public static function flushCount(): int
    {
        return static::$flushCount;
    }

    public static function writeCount(): int
    {
        return static::$writeCount;
    }

    public static function keys(): array
    {
        static::purgeExpired();

        return array_keys(static::$items);
    }

    public function get($key): mixed
    {
        static::purgeExpired();

        return static::$items[$key]['value'] ?? null;
    }

    public function many(array $keys): array
    {
        return array_reduce($keys, function ($results, $key) {
            $results[$key] = $this->get($key);

            return $results;
        }, []);
    }

    public function put($key, $value, $seconds): bool
    {
        static::$items[$key] = [
            'value' => $value,
            'expires_at' => $seconds > 0
                ? time() + $seconds
                : time(),
        ];
        static::$writeCount++;

        return true;
    }

    public function putMany(array $values, $seconds): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1): int|bool
    {
        $current = $this->get($key);

        if (! is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + $value;
        $this->forever($key, $newValue);

        return $newValue;
    }

    public function decrement($key, $value = 1): int|bool
    {
        $current = $this->get($key);

        if (! is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current - $value;
        $this->forever($key, $newValue);

        return $newValue;
    }

    public function forever($key, $value): bool
    {
        static::$items[$key] = [
            'value' => $value,
            'expires_at' => null,
        ];
        static::$writeCount++;

        return true;
    }

    public function forget($key): bool
    {
        unset(static::$items[$key]);

        return true;
    }

    public function flush(): bool
    {
        static::$flushCount++;
        static::$items = [];

        return true;
    }

    public function touch($key, $seconds): bool
    {
        if (! isset(static::$items[$key])) {
            return false;
        }

        static::$items[$key]['expires_at'] = time() + $seconds;

        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }

    protected static function purgeExpired(): void
    {
        $now = time();

        foreach (static::$items as $key => $item) {
            if (
                $item['expires_at'] !== null
                && $item['expires_at'] <= $now
            ) {
                unset(static::$items[$key]);
            }
        }
    }
}
