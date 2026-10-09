<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedRole;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Regression tests for issue #481: Cache invalidation when using custom
 * intermediate table models (via ->using(CustomPivot::class)).
 *
 * Acceptance criteria:
 *   [AC1] Cache is invalidated when pivotAttached, pivotDetached, and pivotSynced
 *         events fire via a custom pivot model.
 *   [AC2] A regression test covering custom intermediate table models (->using()) is added.
 *   [AC3] Existing pivot invalidation behaviour is not regressed.
 */

// -------------------------------------------------------------------------
// Sanity check: relationship with custom pivot returns CachedBelongsToMany
// -------------------------------------------------------------------------
test('custom pivot relationship returns cached belongs to many', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $relation = (new User)->find($userId)->rolesWithCustomPivot();

    expect($relation)->toBeInstanceOf(
        CachedBelongsToMany::class,
        'rolesWithCustomPivot() should return CachedBelongsToMany even when using() is set.',
    );
});

// -------------------------------------------------------------------------
// AC1 + AC2: Cache is invalidated on attach via custom pivot model
// -------------------------------------------------------------------------
test('cache is invalidated when attaching via custom pivot', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->rolesWithCustomPivot();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    // Warm the cache.
    $result = (new User)->find($userId)->rolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    // Attach via the custom-pivot relationship — cache must be busted.
    $newRole = Role::factory()->create();
    (new User)->find($userId)->rolesWithCustomPivot()->attach($newRole->id);

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after attach via custom pivot, but a cached value was found.',
    );
});

// -------------------------------------------------------------------------
// AC1 + AC2: Cache is invalidated on detach via custom pivot model
// -------------------------------------------------------------------------
test('cache is invalidated when detaching via custom pivot', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->rolesWithCustomPivot();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    // Warm the cache.
    $result = (new User)->find($userId)->rolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    $firstRoleId = $result->first()->id;
    (new User)->find($userId)->rolesWithCustomPivot()->detach($firstRoleId);

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after detach via custom pivot, but a cached value was found.',
    );
});

// -------------------------------------------------------------------------
// AC1 + AC2: Cache is invalidated on sync via custom pivot model
// -------------------------------------------------------------------------
test('cache is invalidated when syncing via custom pivot', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->rolesWithCustomPivot();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    // Warm the cache.
    $result = (new User)->find($userId)->rolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    $newRoles = Role::factory()->count(2)->create();
    (new User)->find($userId)->rolesWithCustomPivot()->sync($newRoles->pluck('id'));

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after sync via custom pivot, but a cached value was found.',
    );

    // Verify roles match what was synced.
    expect(array_diff(
        (new User)->find($userId)->rolesWithCustomPivot->pluck('id')->toArray(),
        $newRoles->pluck('id')->toArray()
    ))->toBeEmpty();
});

// -------------------------------------------------------------------------
// AC3: Existing (non-custom-pivot) invalidation still works
// -------------------------------------------------------------------------
test('existing pivot invalidation not regressed', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->roles();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    // Warm the cache.
    $result = (new User)->find($userId)->roles;
    expect($result)->not->toBeEmpty();

    $newRole = Role::factory()->create();
    (new User)->find($userId)->roles()->attach($newRole->id);

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Existing (non-custom-pivot) cache invalidation on attach should still work.',
    );
});

// -------------------------------------------------------------------------
// Issue #551: Related model is NOT cacheable, only parent is
// -------------------------------------------------------------------------
test('custom pivot with uncached related returns correct relation', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $relation = (new User)->find($userId)->uncachedRolesWithCustomPivot();

    expect($relation)->toBeInstanceOf(
        CachedBelongsToMany::class,
        'uncachedRolesWithCustomPivot() should return CachedBelongsToMany when only parent is cacheable.',
    );
});

test('cache invalidated on attach when related model not cacheable', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    // Warm the cache via the uncached-related relationship.
    $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    // Attach a new role.
    $newRole = UncachedRole::create(['name' => 'test-uncached-role']);
    (new User)->find($userId)->uncachedRolesWithCustomPivot()->attach($newRole->id);

    // After attach, fetching fresh data should include the new role.
    $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect($freshResult->contains('id', $newRole->id))->toBeTrue(
        'After attach via uncached related model with custom pivot, fresh query should reflect the change.',
    );
});

test('cache invalidated on detach when related model not cacheable', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    $firstRoleId = $result->first()->id;
    (new User)->find($userId)->uncachedRolesWithCustomPivot()->detach($firstRoleId);

    $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect($freshResult->contains('id', $firstRoleId))->toBeFalse(
        'After detach via uncached related model with custom pivot, fresh query should not contain detached role.',
    );
});

test('cache invalidated on sync when related model not cacheable', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    $newRoles = collect([
        UncachedRole::create(['name' => 'sync-role-1']),
        UncachedRole::create(['name' => 'sync-role-2']),
    ]);
    (new User)->find($userId)->uncachedRolesWithCustomPivot()->sync($newRoles->pluck('id'));

    $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
    expect(array_diff(
        $freshResult->pluck('id')->toArray(),
        $newRoles->pluck('id')->toArray()
    ))->toBeEmpty('After sync via uncached related model with custom pivot, fresh query should reflect synced roles.');
});

// -------------------------------------------------------------------------
// Event-firing assertions for custom pivot operations
// -------------------------------------------------------------------------
test('pivot attached event fires with custom pivot', function () {
    Event::fake();

    $userId = (int) DB::table('role_user')->value('user_id');
    $newRole = Role::factory()->create();

    (new User)->find($userId)->rolesWithCustomPivot()->attach($newRole->id);

    Event::assertDispatched("eloquent.pivotAttached: " . User::class);
});

test('pivot detached event fires with custom pivot', function () {
    Event::fake();

    $userId = (int) DB::table('role_user')->value('user_id');
    $user = (new User)->find($userId);
    $firstRoleId = $user->rolesWithCustomPivot->first()->id;

    $user->rolesWithCustomPivot()->detach($firstRoleId);

    Event::assertDispatched("eloquent.pivotDetached: " . User::class);
});

test('pivot synced event fires with custom pivot', function () {
    Event::fake();

    $userId = (int) DB::table('role_user')->value('user_id');
    $newRoles = Role::factory()->count(2)->create();

    (new User)->find($userId)->rolesWithCustomPivot()->sync($newRoles->pluck('id'));

    Event::assertDispatched("eloquent.pivotSynced: " . User::class);
});

// -------------------------------------------------------------------------
// updateExistingPivot cache invalidation
// -------------------------------------------------------------------------
test('cache is invalidated when updating existing pivot via custom pivot', function () {
    $userId = (int) DB::table('role_user')->value('user_id');

    $relation = (new User)->find($userId)->rolesWithCustomPivot();
    [$tags, $hashedKey] = getCacheTagsAndKey($relation);

    // Warm the cache.
    $result = (new User)->find($userId)->rolesWithCustomPivot;
    expect($result)->not->toBeEmpty();

    $firstRoleId = $result->first()->id;
    (new User)->find($userId)->rolesWithCustomPivot()->updateExistingPivot(
        $firstRoleId,
        ['updated_at' => now()]
    );

    $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

    expect($cachedResult)->toBeNull(
        'Expected cache to be invalidated after updateExistingPivot via custom pivot, but a cached value was found.',
    );
});

// -------------------------------------------------------------------------
// Neither model cacheable: fallback to standard BelongsToMany
// -------------------------------------------------------------------------
test('non cacheable models return standard belongs to many', function () {
    $userId = (int) DB::table('role_user')->value('user_id');
    $user = UncachedUser::find($userId);

    $relation = $user->roles();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation)->not->toBeInstanceOf(
        CachedBelongsToMany::class,
        'UncachedUser->roles() should return a standard BelongsToMany, not CachedBelongsToMany.',
    );

    // Verify attach/detach still work correctly.
    $newRole = UncachedRole::create(['name' => 'fallback-test-role']);
    $user->roles()->attach($newRole->id);

    expect($user->roles->contains('id', $newRole->id))->toBeTrue(
        'Attach should work on standard BelongsToMany for non-cacheable models.',
    );

    $user->roles()->detach($newRole->id);
    $user->unsetRelation('roles');

    expect($user->roles->contains('id', $newRole->id))->toBeFalse(
        'Detach should work on standard BelongsToMany for non-cacheable models.',
    );
});
