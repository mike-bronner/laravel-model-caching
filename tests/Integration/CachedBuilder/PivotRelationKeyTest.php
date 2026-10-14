<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Tag;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedStore;
use Illuminate\Support\Facades\DB;

test('a lazy belongs to many read keys the related global scopes', function () {
    [$book, $store] = bookWithOneStore();

    [$first, $second] = runTwiceExcludingTheFirstResult(Store::class, fn () => $book->fresh()
        ->stores
        ->pluck('id')
        ->all());

    expect($first)->toBe([$store->id])
        ->and($second)->toBe([]);
});

test('a direct belongs to many query keys the related global scopes', function () {
    [$book, $store] = bookWithOneStore();

    [$first, $second] = runTwiceExcludingTheFirstResult(Store::class, fn () => $book->stores()
        ->get()
        ->pluck('id')
        ->all());

    expect($first)->toBe([$store->id])
        ->and($second)->toBe([]);
});

test('a belongs to many eager load keys the related global scopes', function () {
    [$book, $store] = bookWithOneStore();

    [$first, $second] = runTwiceExcludingTheFirstResult(Store::class, fn () => (new Book)
        ->newCollection([$book->fresh()])
        ->load('stores')
        ->first()
        ->stores
        ->pluck('id')
        ->all());

    expect($first)->toBe([$store->id])
        ->and($second)->toBe([]);
});

test('a direct belongs to many query keys the pivot columns it selects', function () {
    [$book] = bookWithOneStore(['test' => 'pivot value']);
    $book->stores()->get();

    $store = $book->stores()
        ->withPivot('test')
        ->get()
        ->first();

    expect($store->pivot->test)->toBe('pivot value');
});

test('a belongs to many eager load keys the pivot columns it selects', function () {
    [$book] = bookWithOneStore(['test' => 'pivot value']);
    (new Book)->with('stores')->find($book->id);

    $store = (new Book)
        ->with(['stores' => fn ($query) => $query->withPivot('test')])
        ->find($book->id)
        ->stores
        ->first();

    expect($store->pivot->test)->toBe('pivot value');
});

test('a morph to many query keys the related global scopes', function () {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tags()->attach($tag);

    [$first, $second] = runTwiceExcludingTheFirstResult(Tag::class, fn () => $post->tags()
        ->get()
        ->pluck('id')
        ->all());

    expect($first)->toBe([$tag->id])
        ->and($second)->toBe([]);
});

test('a morph to many query keys the pivot columns it selects', function () {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tags()->attach($tag, ['created_at' => '2020-01-02 03:04:05']);
    $post->tags()->get();

    $taggedWith = $post->tags()
        ->withPivot('created_at')
        ->get()
        ->first();

    expect((string) $taggedWith->pivot->created_at)->toBe('2020-01-02 03:04:05');
});

test('a belongs to many key onto an unscoped model without pivot columns is unchanged', function () {
    $book = Book::factory()->create();
    $prefix = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite";

    expect(cacheKey($book->stores()))->toBe(
        "{$prefix}:stores:genealabslaravelmodelcachingcachedbelongstomany"
            . "-join_inner_book_store_f6999d9f3303-book_store.book_id_=_{$book->id}",
    );
});

test('a morph to many key onto an unscoped model without pivot columns is unchanged', function () {
    $post = Post::factory()->create();
    $prefix = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite";

    expect(cacheKey($post->tags()))->toBe(
        "{$prefix}:tags:genealabslaravelmodelcachingcachedmorphtomany"
            . "-join_inner_taggables_586c3bb19ca2-taggables.taggable_id_=_{$post->id}"
            . "-taggables.taggable_type_=_GeneaLabs\\LaravelModelCaching\\Tests\\Fixtures\\Post",
    );
});

test('a direct belongs to many query keys its pivot accessor name', function () {
    [$book] = bookWithOneStore();
    $book->stores()->get();

    $store = $book->stores()
        ->as('membership')
        ->get()
        ->first();

    expect($store->relationLoaded('membership'))->toBeTrue();
});

test('a belongs to many eager load keys its pivot accessor name', function () {
    [$book] = bookWithOneStore();
    (new Book)->with('stores')->find($book->id);

    $store = (new Book)
        ->with(['stores' => fn ($query) => $query->as('membership')])
        ->find($book->id)
        ->stores
        ->first();

    expect($store->relationLoaded('membership'))->toBeTrue();
});

test('a direct belongs to many query keys its pivot class', function () {
    [$book] = bookWithOneStore();
    $book->stores()->get();

    $store = $book->stores()
        ->using(RoleUser::class)
        ->get()
        ->first();

    expect($store->pivot)->toBeInstanceOf(RoleUser::class);
});

test('a belongs to many eager load keys its pivot class', function () {
    [$book] = bookWithOneStore();
    (new Book)->with('stores')->find($book->id);

    $store = (new Book)
        ->with(['stores' => fn ($query) => $query->using(RoleUser::class)])
        ->find($book->id)
        ->stores
        ->first();

    expect($store->pivot)->toBeInstanceOf(RoleUser::class);
});

test('a morph to many query keys its pivot accessor name', function () {
    $post = Post::factory()->create();
    $post->tags()->attach(Tag::factory()->create());
    $post->tags()->get();

    $tag = $post->tags()
        ->as('tagging')
        ->get()
        ->first();

    expect($tag->relationLoaded('tagging'))->toBeTrue();
});

test('a belongs to many key with the default pivot accessor and class is unchanged', function () {
    $book = Book::factory()->create();

    expect(cacheKey($book->stores()->as('pivot')))->toBe(cacheKey($book->stores()));
});

test('a belongs to many query keys its eager loads', function () {
    [$book] = bookWithOneStore();
    $book->stores()->get();

    $store = $book->stores()
        ->with('books')
        ->get()
        ->first();

    expect($store->relationLoaded('books'))->toBeTrue()
        ->and(cacheKey($book->stores()->with('books')))->not->toBe(cacheKey($book->stores()));
});

test('a morph to many query keys its eager loads', function () {
    $post = Post::factory()->create();
    $post->tags()->attach(Tag::factory()->create());
    $post->tags()->get();

    $tag = $post->tags()
        ->with('posts')
        ->get()
        ->first();

    expect($tag->relationLoaded('posts'))->toBeTrue()
        ->and(cacheKey($post->tags()->with('posts')))->not->toBe(cacheKey($post->tags()));
});

test('a write to an eager loaded model clears a belongs to many query that loads it', function () {
    [$book] = bookWithOneStore();
    $readTitle = fn () => $book->stores()
        ->with('books')
        ->get()
        ->first()
        ->books
        ->first()
        ->title;
    $readTitle();
    $book->update(['title' => 'Changed Title']);

    expect($readTitle())->toBe('Changed Title');
});

test('a write to an eager loaded model clears a morph to many query that loads it', function () {
    $post = Post::factory()->create();
    $post->tags()->attach(Tag::factory()->create());
    $readTitle = fn () => $post->tags()
        ->with('posts')
        ->get()
        ->first()
        ->posts
        ->first()
        ->title;
    $readTitle();
    $post->update(['title' => 'Changed Title']);

    expect($readTitle())->toBe('Changed Title');
});

test('a belongs to many query that eager loads an uncached model is not cached', function () {
    Book::resolveRelationUsing('uncachedStoresOfBook', fn (Book $book) => $book
        ->belongsToMany(UncachedStore::class, 'book_store', 'book_id', 'store_id'));
    [$book, $store] = bookWithOneStore();
    $store->books()->with('uncachedStoresOfBook')->get();
    DB::enableQueryLog();

    $store->books()->with('uncachedStoresOfBook')->get();

    expect(DB::getQueryLog())->not->toBe([]);
});

test('a belongs to many query keys the default eager loads of its related model', function () {
    [$book, $store] = bookWithOneStore();
    $storeKey = cacheKey($book->stores());

    $storeWithBooks = $book->storesWithBooks()
        ->get()
        ->first();

    expect($storeWithBooks->relationLoaded('books'))->toBeTrue()
        ->and(cacheKey($book->storesWithBooks()))->toMatch('/-with_[0-9a-f]{40}$/')
        ->and($storeKey)->not->toContain('-with_');
});

test('a write to a default eager loaded model clears a belongs to many query that loads it', function () {
    [$book] = bookWithOneStore();
    $readTitle = fn () => $book->storesWithBooks()
        ->get()
        ->first()
        ->books
        ->first()
        ->title;
    $readTitle();
    $book->update(['title' => 'Changed Title']);

    expect($readTitle())->toBe('Changed Title');
});
