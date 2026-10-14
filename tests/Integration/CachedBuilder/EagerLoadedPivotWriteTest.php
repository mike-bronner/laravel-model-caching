<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookOnTenantConnection;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithStorePrefixedPivot;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableBookStorePivot;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableTaggable;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StorePrefixedBookStorePivot;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;

test('eager-loaded belongs to many tags include the pivot table', function (): void {
    $query = (new Book)->with("stores");

    expect(makeCacheTags($query))->toBe([
        tag("genealabslaravelmodelcachingtestsfixturesbook"),
        tag("genealabslaravelmodelcachingtestsfixturesstore"),
        tag("books"),
        tag("book-store"),
    ]);
});

test('a cachable pivot model create refreshes an eager-loaded belongs to many', function (): void {
    expect(eagerLoadedMembership(new User, "roles"))->toBe(userRoleMembership());

    (new CachableRoleUser)->create(newRoleUserRow());

    expect(eagerLoadedMembership(new User, "roles"))->toBe(userRoleMembership());
});

test('a cachable pivot model delete refreshes an eager-loaded belongs to many', function (): void {
    expect(eagerLoadedMembership(new User, "roles"))->toBe(userRoleMembership());

    (new CachableRoleUser)->first()
        ->delete();

    expect(eagerLoadedMembership(new User, "roles"))->toBe(userRoleMembership());
});

test('a cachable pivot class create refreshes an eager-loaded belongs to many', function (): void {
    expect(eagerLoadedMembership(new Book, "stores"))->toBe(bookStoreMembership());

    (new CachableBookStorePivot)->create(newBookStoreRow());

    expect(eagerLoadedMembership(new Book, "stores"))->toBe(bookStoreMembership());
});

test('a cachable pivot class delete refreshes an eager-loaded belongs to many', function (): void {
    expect(eagerLoadedMembership(new Book, "stores"))->toBe(bookStoreMembership());

    $row = (new CachableBookStorePivot)->where("book_id", bookWithStores());
    $row->first()
        ->delete();

    expect(eagerLoadedMembership(new Book, "stores"))->toBe(bookStoreMembership());
});

test('a cachable pivot model create refreshes an eager-loaded morph to many', function (): void {
    expect(eagerLoadedMembership(new Post, "tags"))->toBe(postTagMembership());

    (new CachableTaggable)->create(newPostTagRow());

    expect(eagerLoadedMembership(new Post, "tags"))->toBe(postTagMembership());
});

test('a pivot write refreshes the eager load of a parent with its own prefix', function (): void {
    expect(eagerLoadedMembership(new PrefixedStore, "books"))->toBe(storeBookMembership());

    (new CachableBookStorePivot)->create(newBookStoreRow());

    expect(eagerLoadedMembership(new PrefixedStore, "books"))->toBe(storeBookMembership());
});

test('a pivot write refreshes eager-loaded related models with a prefix', function (): void {
    expect(eagerLoadedMembership(new Book, "prefixedStores"))->toBe(bookStoreMembership());

    (new CachableBookStorePivot)->create(newBookStoreRow());

    expect(eagerLoadedMembership(new Book, "prefixedStores"))->toBe(bookStoreMembership());
});

test('a relation pivot delete refreshes a parent on another connection', function (): void {
    useTenantConnection();
    $tenantMembership = fn (): array => eagerLoadedMembership(new BookOnTenantConnection, "stores");
    expect($tenantMembership())->toBe(bookStoreMembership());

    $book = (new BookOnTenantConnection)->with("stores");
    $store = $book->find(bookWithStores())
        ->stores
        ->first();
    $store->pivot
        ->delete();

    expect($tenantMembership())->toBe(bookStoreMembership());
});

test('a pivot class create with its own prefix refreshes an eager load', function (): void {
    $membership = fn (): array => eagerLoadedMembership(new BookWithStorePrefixedPivot, "stores");
    expect($membership())->toBe(bookStoreMembership());

    (new StorePrefixedBookStorePivot)->create(newBookStoreRow());

    expect($membership())->toBe(bookStoreMembership());
});

test('a pivot class delete with its own prefix refreshes an eager load', function (): void {
    $membership = fn (): array => eagerLoadedMembership(new BookWithStorePrefixedPivot, "stores");
    expect($membership())->toBe(bookStoreMembership());

    $row = (new StorePrefixedBookStorePivot)->where("book_id", bookWithStores());
    $row->first()
        ->delete();

    expect($membership())->toBe(bookStoreMembership());
});

test('a pivot write under the parent prefix refreshes the parent eager load', function (): void {
    expect(eagerLoadedMembership(new PrefixedStore, "books"))->toBe(storeBookMembership());

    (new StorePrefixedBookStorePivot)->create(newBookStoreRow());

    expect(eagerLoadedMembership(new PrefixedStore, "books"))->toBe(storeBookMembership());
});
