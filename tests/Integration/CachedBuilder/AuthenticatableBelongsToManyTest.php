<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

test('roles relationship returns cached belongs to many', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $relation = (new User)->find($userId)->roles();

    expect($relation)->toBeInstanceOf(
        CachedBelongsToMany::class,
        'Expected roles() on an Authenticatable+Cachable model to return CachedBelongsToMany.',
    );
});

test('belongs to many on authenticatable model caches results', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->roles();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    $roles = (new User)->find($userId)->roles;
    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);
    $cachedRoles = $cachedResult['value'] ?? null;
    $uncachedRoles = (new UncachedUser)->find($userId)->roles;

    expect($cachedRoles)->not->toBeNull(
        'Expected roles to be stored in cache, but cache was empty.',
    );
    expect($roles)->not->toBeEmpty();
    expect($roles->pluck('id'))->toEqual($uncachedRoles->pluck('id'));
    expect($cachedRoles->pluck('id'))->toEqual($uncachedRoles->pluck('id'));
});

test('cache is invalidated when attaching role', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->roles();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    $result = (new User)->find($userId)->roles;
    expect($result)->not->toBeEmpty();

    $newRole = Role::factory()->create();
    (new User)->find($userId)->roles()->attach($newRole->id);

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after attach, but a cached value was found.',
    );
});

test('cache is invalidated when detaching role', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->roles();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    $result = (new User)->find($userId)->roles;
    expect($result)->not->toBeEmpty();

    $firstRoleId = $result->first()->id;
    (new User)->find($userId)->roles()->detach($firstRoleId);

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after detach, but a cached value was found.',
    );
});

test('cache is invalidated when syncing roles', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->roles();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    $result = (new User)->find($userId)->roles;
    expect($result)->not->toBeEmpty();

    $newRoles = Role::factory()->count(2)->create();
    (new User)->find($userId)->roles()->sync($newRoles->pluck('id'));

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after sync, but a cached value was found.',
    );

    expect(array_diff(
        (new User)->find($userId)->roles->pluck('id')->toArray(),
        $newRoles->pluck('id')->toArray()
    ))->toBeEmpty();
});
