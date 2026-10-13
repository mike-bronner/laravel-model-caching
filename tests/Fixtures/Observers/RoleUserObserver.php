<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures\Observers;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RoleUser;

class RoleUserObserver
{
    public static array $events = [];

    public static function reset(): void
    {
        static::$events = [];
    }

    public function creating(RoleUser $pivot): void
    {
        static::$events['creating'] = (static::$events['creating'] ?? 0) + 1;
    }

    public function created(RoleUser $pivot): void
    {
        static::$events['created'] = (static::$events['created'] ?? 0) + 1;
    }

    public function deleting(RoleUser $pivot): void
    {
        static::$events['deleting'] = (static::$events['deleting'] ?? 0) + 1;
    }

    public function deleted(RoleUser $pivot): void
    {
        static::$events['deleted'] = (static::$events['deleted'] ?? 0) + 1;
    }

    public function updating(RoleUser $pivot): void
    {
        static::$events['updating'] = (static::$events['updating'] ?? 0) + 1;
    }

    public function updated(RoleUser $pivot): void
    {
        static::$events['updated'] = (static::$events['updated'] ?? 0) + 1;
    }
}
