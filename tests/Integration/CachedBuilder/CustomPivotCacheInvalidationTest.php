<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedRole;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
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
class CustomPivotCacheInvalidationTest extends IntegrationTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userIdWithRoles(): int
    {
        return (int) DB::table('role_user')->value('user_id');
    }

    private function getCacheTagsAndKey(CachedBelongsToMany $relation): array
    {
        $reflTags = new \ReflectionMethod($relation, 'makeCacheTags');
        $tags = $reflTags->invoke($relation);

        $reflKey = new \ReflectionMethod($relation, 'makeCacheKey');
        $rawKey = $reflKey->invoke($relation);

        return [$tags, sha1($rawKey)];
    }

    // -------------------------------------------------------------------------
    // Sanity check: relationship with custom pivot returns CachedBelongsToMany
    // -------------------------------------------------------------------------

    public function testCustomPivotRelationshipReturnsCachedBelongsToMany(): void
    {
        $userId = $this->userIdWithRoles();
        $relation = (new User)->find($userId)->rolesWithCustomPivot();

        $this->assertInstanceOf(
            CachedBelongsToMany::class,
            $relation,
            'rolesWithCustomPivot() should return CachedBelongsToMany even when using() is set.'
        );
    }

    // -------------------------------------------------------------------------
    // AC1 + AC2: Cache is invalidated on attach via custom pivot model
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenAttachingViaCustomPivot(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->rolesWithCustomPivot();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->rolesWithCustomPivot;
        $this->assertNotEmpty($result);

        // Attach via the custom-pivot relationship — cache must be busted.
        $newRole = Role::factory()->create();
        (new User)->find($userId)->rolesWithCustomPivot()->attach($newRole->id);

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull(
            $cachedResult,
            'Expected cache to be invalidated after attach via custom pivot, but a cached value was found.'
        );
    }

    // -------------------------------------------------------------------------
    // AC1 + AC2: Cache is invalidated on detach via custom pivot model
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenDetachingViaCustomPivot(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->rolesWithCustomPivot();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->rolesWithCustomPivot;
        $this->assertNotEmpty($result);

        $firstRoleId = $result->first()->id;
        (new User)->find($userId)->rolesWithCustomPivot()->detach($firstRoleId);

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull(
            $cachedResult,
            'Expected cache to be invalidated after detach via custom pivot, but a cached value was found.'
        );
    }

    // -------------------------------------------------------------------------
    // AC1 + AC2: Cache is invalidated on sync via custom pivot model
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenSyncingViaCustomPivot(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->rolesWithCustomPivot();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->rolesWithCustomPivot;
        $this->assertNotEmpty($result);

        $newRoles = Role::factory()->count(2)->create();
        (new User)->find($userId)->rolesWithCustomPivot()->sync($newRoles->pluck('id'));

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull(
            $cachedResult,
            'Expected cache to be invalidated after sync via custom pivot, but a cached value was found.'
        );

        // Verify roles match what was synced.
        $this->assertEmpty(array_diff(
            (new User)->find($userId)->rolesWithCustomPivot->pluck('id')->toArray(),
            $newRoles->pluck('id')->toArray()
        ));
    }

    // -------------------------------------------------------------------------
    // AC3: Existing (non-custom-pivot) invalidation still works
    // -------------------------------------------------------------------------

    public function testExistingPivotInvalidationNotRegressed(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->roles();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->roles;
        $this->assertNotEmpty($result);

        $newRole = Role::factory()->create();
        (new User)->find($userId)->roles()->attach($newRole->id);

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull(
            $cachedResult,
            'Existing (non-custom-pivot) cache invalidation on attach should still work.'
        );
    }

    // -------------------------------------------------------------------------
    // Issue #551: Related model is NOT cacheable, only parent is
    // -------------------------------------------------------------------------

    public function testCustomPivotWithUncachedRelatedReturnsCorrectRelation(): void
    {
        $userId = $this->userIdWithRoles();
        $relation = (new User)->find($userId)->uncachedRolesWithCustomPivot();

        $this->assertInstanceOf(
            CachedBelongsToMany::class,
            $relation,
            'uncachedRolesWithCustomPivot() should return CachedBelongsToMany when only parent is cacheable.'
        );
    }

    public function testCacheInvalidatedOnAttachWhenRelatedModelNotCacheable(): void
    {
        $userId = $this->userIdWithRoles();

        // Warm the cache via the uncached-related relationship.
        $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertNotEmpty($result);

        // Attach a new role.
        $newRole = UncachedRole::create(['name' => 'test-uncached-role']);
        (new User)->find($userId)->uncachedRolesWithCustomPivot()->attach($newRole->id);

        // After attach, fetching fresh data should include the new role.
        $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertTrue(
            $freshResult->contains('id', $newRole->id),
            'After attach via uncached related model with custom pivot, fresh query should reflect the change.'
        );
    }

    public function testCacheInvalidatedOnDetachWhenRelatedModelNotCacheable(): void
    {
        $userId = $this->userIdWithRoles();

        $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertNotEmpty($result);

        $firstRoleId = $result->first()->id;
        (new User)->find($userId)->uncachedRolesWithCustomPivot()->detach($firstRoleId);

        $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertFalse(
            $freshResult->contains('id', $firstRoleId),
            'After detach via uncached related model with custom pivot, fresh query should not contain detached role.'
        );
    }

    public function testCacheInvalidatedOnSyncWhenRelatedModelNotCacheable(): void
    {
        $userId = $this->userIdWithRoles();

        $result = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertNotEmpty($result);

        $newRoles = collect([
            UncachedRole::create(['name' => 'sync-role-1']),
            UncachedRole::create(['name' => 'sync-role-2']),
        ]);
        (new User)->find($userId)->uncachedRolesWithCustomPivot()->sync($newRoles->pluck('id'));

        $freshResult = (new User)->find($userId)->uncachedRolesWithCustomPivot;
        $this->assertEmpty(array_diff(
            $freshResult->pluck('id')->toArray(),
            $newRoles->pluck('id')->toArray()
        ), 'After sync via uncached related model with custom pivot, fresh query should reflect synced roles.');
    }

    // -------------------------------------------------------------------------
    // Event-firing assertions for custom pivot operations
    // -------------------------------------------------------------------------

    public function testPivotAttachedEventFiresWithCustomPivot(): void
    {
        Event::fake();

        $userId = $this->userIdWithRoles();
        $newRole = Role::factory()->create();

        (new User)->find($userId)->rolesWithCustomPivot()->attach($newRole->id);

        Event::assertDispatched("eloquent.pivotAttached: " . User::class);
    }

    public function testPivotDetachedEventFiresWithCustomPivot(): void
    {
        Event::fake();

        $userId = $this->userIdWithRoles();
        $user = (new User)->find($userId);
        $firstRoleId = $user->rolesWithCustomPivot->first()->id;

        $user->rolesWithCustomPivot()->detach($firstRoleId);

        Event::assertDispatched("eloquent.pivotDetached: " . User::class);
    }

    public function testPivotSyncedEventFiresWithCustomPivot(): void
    {
        Event::fake();

        $userId = $this->userIdWithRoles();
        $newRoles = Role::factory()->count(2)->create();

        (new User)->find($userId)->rolesWithCustomPivot()->sync($newRoles->pluck('id'));

        Event::assertDispatched("eloquent.pivotSynced: " . User::class);
    }

    // -------------------------------------------------------------------------
    // updateExistingPivot cache invalidation
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenUpdatingExistingPivotViaCustomPivot(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->rolesWithCustomPivot();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->rolesWithCustomPivot;
        $this->assertNotEmpty($result);

        $firstRoleId = $result->first()->id;
        (new User)->find($userId)->rolesWithCustomPivot()->updateExistingPivot(
            $firstRoleId,
            ['updated_at' => now()]
        );

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull(
            $cachedResult,
            'Expected cache to be invalidated after updateExistingPivot via custom pivot, but a cached value was found.'
        );
    }

    // -------------------------------------------------------------------------
    // Neither model cacheable: fallback to standard BelongsToMany
    // -------------------------------------------------------------------------

    public function testNonCacheableModelsReturnStandardBelongsToMany(): void
    {
        $userId = $this->userIdWithRoles();
        $user = UncachedUser::find($userId);

        $relation = $user->roles();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertNotInstanceOf(
            CachedBelongsToMany::class,
            $relation,
            'UncachedUser->roles() should return a standard BelongsToMany, not CachedBelongsToMany.'
        );

        // Verify attach/detach still work correctly.
        $newRole = UncachedRole::create(['name' => 'fallback-test-role']);
        $user->roles()->attach($newRole->id);

        $this->assertTrue(
            $user->roles->contains('id', $newRole->id),
            'Attach should work on standard BelongsToMany for non-cacheable models.'
        );

        $user->roles()->detach($newRole->id);
        $user->unsetRelation('roles');

        $this->assertFalse(
            $user->roles->contains('id', $newRole->id),
            'Detach should work on standard BelongsToMany for non-cacheable models.'
        );
    }
}