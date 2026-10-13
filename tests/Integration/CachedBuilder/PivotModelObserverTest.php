<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Observers\RoleUserObserver;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    RoleUser::observe(RoleUserObserver::class);
    RoleUserObserver::reset();
});

afterEach(function () {
    RoleUserObserver::reset();
});

test('pivot observer fires on sync with caching enabled', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $user = (new User)->find($userId);

    $user->rolesWithCustomPivot;

    $newRoles = Role::factory()->count(2)->create();
    RoleUserObserver::reset();
    $user->rolesWithCustomPivot()->sync($newRoles->pluck('id')->toArray());

    expect(RoleUserObserver::$events['creating'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "creating" event should fire during sync() with caching enabled.',
    );
    expect(RoleUserObserver::$events['created'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "created" event should fire during sync() with caching enabled.',
    );
});

test('pivot observer fires on attach with caching enabled', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $user = (new User)->find($userId);

    $newRole = Role::factory()->create();
    RoleUserObserver::reset();
    $user->rolesWithCustomPivot()->attach($newRole->id);

    expect(RoleUserObserver::$events['creating'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "creating" event should fire during attach() with caching enabled.',
    );
    expect(RoleUserObserver::$events['created'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "created" event should fire during attach() with caching enabled.',
    );
});

test('pivot observer fires on detach with caching enabled', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $user = (new User)->find($userId);
    $roles = $user->rolesWithCustomPivot;
    expect($roles)->not->toBeEmpty();

    $firstRoleId = $roles->first()->id;
    RoleUserObserver::reset();
    $user->rolesWithCustomPivot()->detach($firstRoleId);

    expect(RoleUserObserver::$events['deleting'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "deleting" event should fire during detach() with caching enabled.',
    );
    expect(RoleUserObserver::$events['deleted'] ?? 0)->toBeGreaterThan(
        0,
        'Pivot observer "deleted" event should fire during detach() with caching enabled.',
    );
});
