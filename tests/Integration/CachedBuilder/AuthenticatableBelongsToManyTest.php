<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Covers the scenario from issue #473: an Authenticatable-based model using
 * the Cachable trait should correctly cache belongsToMany relationships, with
 * cache invalidation on attach / detach / sync.
 */
class AuthenticatableBelongsToManyTest extends IntegrationTestCase
{
    /**
     * Return the id of a seeded user that has at least one role attached.
     */
    private function userIdWithRoles(): int
    {
        return (int) DB::table('role_user')->value('user_id');
    }

    /**
     * Use reflection to get the actual cache tags and key from the relationship
     * instance, so we're not hard-coding implementation details.
     */
    private function getCacheTagsAndKey(CachedBelongsToMany $relation): array
    {
        $reflTags = new \ReflectionMethod($relation, 'makeCacheTags');
        $tags = $reflTags->invoke($relation);

        $reflKey = new \ReflectionMethod($relation, 'makeCacheKey');
        $rawKey = $reflKey->invoke($relation);

        return [$tags, sha1($rawKey)];
    }

    // -------------------------------------------------------------------------
    // Sanity check: the relationship returns the right class
    // -------------------------------------------------------------------------

    public function testRolesRelationshipReturnsCachedBelongsToMany(): void
    {
        $userId = $this->userIdWithRoles();
        $relation = (new User)->find($userId)->roles();

        $this->assertInstanceOf(
            CachedBelongsToMany::class,
            $relation,
            'Expected roles() on an Authenticatable+Cachable model to return CachedBelongsToMany.'
        );
    }

    // -------------------------------------------------------------------------
    // AC1: Query is cached
    // -------------------------------------------------------------------------

    public function testBelongsToManyOnAuthenticatableModelCachesResults(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->roles();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Access the relationship — this should warm the cache.
        $roles = (new User)->find($userId)->roles;
        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);
        $cachedRoles = $cachedResult['value'] ?? null;
        $uncachedRoles = (new UncachedUser)->find($userId)->roles;

        $this->assertNotNull($cachedRoles, 'Expected roles to be stored in cache, but cache was empty.');
        $this->assertNotEmpty($roles);
        $this->assertEquals($uncachedRoles->pluck('id'), $roles->pluck('id'));
        $this->assertEquals($uncachedRoles->pluck('id'), $cachedRoles->pluck('id'));
    }

    // -------------------------------------------------------------------------
    // AC2a: Cache is invalidated on attach
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenAttachingRole(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->roles();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->roles;
        $this->assertNotEmpty($result);

        // Attach a new role — this should bust the cache.
        $newRole = Role::factory()->create();
        (new User)->find($userId)->roles()->attach($newRole->id);

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull($cachedResult, 'Expected cache to be invalidated after attach, but a cached value was found.');
    }

    // -------------------------------------------------------------------------
    // AC2b: Cache is invalidated on detach
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenDetachingRole(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->roles();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->roles;
        $this->assertNotEmpty($result);

        $firstRoleId = $result->first()->id;
        (new User)->find($userId)->roles()->detach($firstRoleId);

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull($cachedResult, 'Expected cache to be invalidated after detach, but a cached value was found.');
    }

    // -------------------------------------------------------------------------
    // AC2c: Cache is invalidated on sync
    // -------------------------------------------------------------------------

    public function testCacheIsInvalidatedWhenSyncingRoles(): void
    {
        $userId = $this->userIdWithRoles();

        $relation = (new User)->find($userId)->roles();
        [$tags, $hashedKey] = $this->getCacheTagsAndKey($relation);

        // Warm the cache.
        $result = (new User)->find($userId)->roles;
        $this->assertNotEmpty($result);

        $newRoles = Role::factory()->count(2)->create();
        (new User)->find($userId)->roles()->sync($newRoles->pluck('id'));

        $cachedResult = $this->cache()->tags($tags)->get($hashedKey);

        $this->assertNull($cachedResult, 'Expected cache to be invalidated after sync, but a cached value was found.');

        // Verify the final roles match what was synced.
        $this->assertEmpty(array_diff(
            (new User)->find($userId)->roles->pluck('id')->toArray(),
            $newRoles->pluck('id')->toArray()
        ));
    }
}