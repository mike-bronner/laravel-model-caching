<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithConditionalFillable;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithDynamicFillable;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthorWithDynamicFillable;

afterEach(function () {
    AuthorWithConditionalFillable::$adminMode = false;
});

test('dynamic fillable in constructor works on first save', function () {
    $author = new AuthorWithDynamicFillable();
    $author->fill([
        'name' => 'Test Author',
        'email' => 'test@example.com',
        'is_famous' => true,
    ]);

    expect($author->is_famous)->toBeTrue(
        'Dynamic fillable field should be set on first fill when using Cachable trait',
    );
});

test('dynamic fillable matches uncached behavior', function () {
    $uncached = new UncachedAuthorWithDynamicFillable();
    $uncached->fill([
        'name' => 'Uncached Author',
        'email' => 'uncached@example.com',
        'is_famous' => true,
    ]);

    $cached = new AuthorWithDynamicFillable();
    $cached->fill([
        'name' => 'Cached Author',
        'email' => 'cached@example.com',
        'is_famous' => true,
    ]);

    expect($cached->is_famous)->toEqual(
        $uncached->is_famous,
        'Cachable and non-Cachable models should behave identically with dynamic fillable',
    );
});

test('dynamic fillable field is saved on first attempt', function () {
    $author = AuthorWithDynamicFillable::create([
        'name' => 'First Save Author',
        'email' => 'firstsave@example.com',
        'is_famous' => true,
    ]);

    $fromDb = (new UncachedAuthorWithDynamicFillable())
        ->newQuery()
        ->where('email', 'firstsave@example.com')
        ->first();

    expect($fromDb)->not->toBeNull('Author should exist in database after first save');
    expect((bool) $fromDb->is_famous)->toBeTrue(
        'Dynamic fillable field should be persisted on first save, not require a second save',
    );
});

test('dynamic fillable field works with update', function () {
    $author = AuthorWithDynamicFillable::create([
        'name' => 'Update Author',
        'email' => 'update@example.com',
        'is_famous' => false,
    ]);

    $author->update(['is_famous' => true]);

    $fromDb = (new UncachedAuthorWithDynamicFillable())
        ->newQuery()
        ->where('email', 'update@example.com')
        ->first();

    expect((bool) $fromDb->is_famous)->toBeTrue(
        'Dynamic fillable field should be updated on first update attempt',
    );
});

test('constructor fillable changes not lost by trait initialization', function () {
    $author = new AuthorWithDynamicFillable();
    $fillable = $author->getFillable();

    // Dynamic fillable field added in constructor should persist after trait initialization
    expect($fillable)->toContain('is_famous');
});

/**
 * Regression test for #534: model cached in non-admin context must
 * reflect admin-context fillable when deserialized by an admin.
 *
 * Without the __wakeup fix, the deserialized model retains the stale
 * $fillable from the original (non-admin) context, silently dropping
 * admin-only fields on mass assignment.
 */
test('deserialized model reruns constructor for dynamic fillable', function () {
    // Cache the model in non-admin context — is_famous NOT in $fillable
    AuthorWithConditionalFillable::$adminMode = false;
    $original = new AuthorWithConditionalFillable();
    expect($original->getFillable())->not->toContain('is_famous');

    $serialized = serialize($original);

    // Switch to admin context
    AuthorWithConditionalFillable::$adminMode = true;

    // Deserialize — simulates what cache retrieval does
    $deserialized = unserialize($serialized);

    // With __wakeup, the constructor re-runs in admin context
    // Deserialized model must re-run constructor so dynamic $fillable reflects current context
    expect($deserialized->getFillable())->toContain('is_famous');
});

/**
 * Regression test for #534: admin-only fillable field must be mass-
 * assignable on a model retrieved from cache that was originally
 * cached in a non-admin context.
 */
test('cached model update works after context change', function () {
    // Create author in admin mode
    AuthorWithConditionalFillable::$adminMode = true;
    $author = AuthorWithConditionalFillable::create([
        'name' => 'Context Test Author',
        'email' => 'context@example.com',
        'is_famous' => false,
    ]);

    // Flush cache, switch to non-admin, populate cache
    $author->flushCache();
    AuthorWithConditionalFillable::$adminMode = false;
    AuthorWithConditionalFillable::where('email', 'context@example.com')->first();

    // Switch back to admin, fetch from cache, update admin-only field
    AuthorWithConditionalFillable::$adminMode = true;
    $fromCache = AuthorWithConditionalFillable::where('email', 'context@example.com')->first();
    $fromCache->update(['is_famous' => true]);

    // Verify in DB
    $fromDb = (new UncachedAuthorWithDynamicFillable())
        ->newQuery()
        ->where('email', 'context@example.com')
        ->first();

    expect((bool) $fromDb->is_famous)->toBeTrue(
        'Admin-only fillable field must work on model cached in non-admin context',
    );
});
