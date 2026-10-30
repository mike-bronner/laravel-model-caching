<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorEagerLoadingItself;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Image;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Printer;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->globalScopes = Model::getAllGlobalScopes();
});

afterEach(function () {
    Model::setAllGlobalScopes($this->globalScopes);
});

test('a cache hit with an unchanged scope on an eager-loaded model reads the cache once', function () {
    $author = Author::factory()->create();
    Book::factory()->create(['author_id' => $author->id]);
    Book::addGlobalScope('keepsEveryRow', fn ($builder) => $builder->where('books.id', '!=', 0));

    [$result, $cacheReads, $queries] = cacheReadsAndQueriesOfTheSecondRun(fn () => (new Author)
        ->with('books')
        ->find($author->id));

    expect($result->books)->toHaveCount(1)
        ->and($cacheReads)->toBe(1)
        ->and($queries)->toBe([]);
});

test('a cache hit with an unchanged scope on a second-level eager-loaded model reads the cache once', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Publisher::addGlobalScope('keepsEveryRow', fn ($builder) => $builder->where('publishers.id', '!=', 0));

    [$result, $cacheReads, $queries] = cacheReadsAndQueriesOfTheSecondRun(fn () => (new Author)
        ->with('books.publisher')
        ->find($author->id));

    expect($result->books->first()->publisher->id)->toBe($book->publisher_id)
        ->and($cacheReads)->toBe(1)
        ->and($queries)->toBe([]);
});

test('a cache hit follows a scope binding that changes between write and hit', function () {
    $author = Author::factory()->create();
    $alpha = Book::factory()->create(['author_id' => $author->id, 'title' => 'Alpha']);
    $bravo = Book::factory()->create(['author_id' => $author->id, 'title' => 'Bravo']);

    $results = resultsPerScopeBinding(Book::class, 'title', ['Alpha', 'Bravo'], fn () => (new Author)
        ->with('books')
        ->find($author->id)
        ->books
        ->pluck('id')
        ->all());

    expect($results)->toBe([[$alpha->id], [$bravo->id]]);
});

test('a cache hit follows a second-level scope binding that changes between write and hit', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    $alpha = Store::factory()->create(['name' => 'Alpha']);
    $bravo = Store::factory()->create(['name' => 'Bravo']);
    $book->stores()->attach([$alpha->id, $bravo->id]);

    $results = resultsPerScopeBinding(Store::class, 'name', ['Alpha', 'Bravo'], fn () => (new Author)
        ->with('books.stores')
        ->find($author->id)
        ->books
        ->flatMap
        ->stores
        ->pluck('id')
        ->all());

    expect($results)->toBe([[$alpha->id], [$bravo->id]]);
});

test('a cache hit follows a global scope added between write and hit', function () {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $query = fn () => (new Author)->with('books')->find($author->id)->books->pluck('id')->all();
    $query();

    Book::addGlobalScope('excludesOneRow', fn ($builder) => $builder->where('books.id', '!=', $excluded->id));

    expect($query())->toBe([$kept->id]);
});

test('a cache hit follows a global scope removed between write and hit', function () {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $query = fn () => (new Author)->with('books')->find($author->id)->books->pluck('id')->sort()->values()->all();
    Book::addGlobalScope('excludesOneRow', fn ($builder) => $builder->where('books.id', '!=', $excluded->id));
    $query();

    Model::setAllGlobalScopes($this->globalScopes);

    expect($query())->toBe([$kept->id, $excluded->id]);
});

test('a cache hit follows a changed scope on a morph to target', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
    ]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Post::class, fn () => [(new Comment)
        ->with('commentable')
        ->find($comment->id)
        ->commentable
        ?->id]);

    expect($first)->toBe([$post->id])
        ->and($second)->toBe([]);
});

test('a cache hit follows a changed scope on an eager load nested under a morph to', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    $image = $book->image()->create(['path' => 'cover.png']);

    [$first, $second] = runTwiceExcludingTheFirstResult(Author::class, fn () => [(new Image)
        ->with('imagable.author')
        ->find($image->id)
        ->imagable
        ->author
        ?->id]);

    expect($first)->toBe([$author->id])
        ->and($second)->toBe([]);
});

test('a cache hit applies the current filter of a scope that filters after the query', function () {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $excludedId = 0;
    Book::addGlobalScope('filtersAfterTheQuery', function ($builder) use (&$excludedId) {
        $builder->afterQuery(function ($books) use (&$excludedId) {
            return $books->reject(fn (Book $book) => $book->id === $excludedId)->values();
        });
    });
    $query = fn () => (new Author)->with('books')->find($author->id)->books->pluck('id')->sort()->values()->all();

    $first = $query();
    $excludedId = $excluded->id;

    expect($first)->toBe([$kept->id, $excluded->id])
        ->and($query())->toBe([$kept->id]);
});

test('a cache hit follows a scope whose own query changes its result between write and hit', function () {
    $author = Author::factory()->create();
    $alpha = Publisher::factory()->create(['name' => 'Alpha']);
    Book::factory()->create(['author_id' => $author->id, 'publisher_id' => $alpha->id]);
    $store = Store::factory()->create(['name' => 'Alpha']);
    $limitReached = addScopeFailingPastLimits(Publisher::class, 5, 100);
    Publisher::addGlobalScope('excludesTheListedName', fn ($builder) => $builder->where(
        'publishers.name',
        '!=',
        (new Store)->with('books.publisher')->where('id', $store->id)->first()?->name ?? '',
    ));
    $query = fn () => (new Author)->with('books.publisher')->find($author->id)->books->first()->publisher?->id;

    $first = $query();
    $store->update(['name' => 'Bravo']);

    expect($first)->toBeNull()
        ->and($query())->toBe($alpha->id)
        ->and($limitReached())->toBeFalse();
});

test('a cache hit follows a changed scope at a repeated model class on the eager load path', function () {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $excludedId = 0;
    Book::addGlobalScope('excludesOneRow', function ($builder) use (&$excludedId) {
        $builder->where('books.id', '!=', $excludedId);
    });
    $query = fn () => (new Author)
        ->with([
            'books' => fn ($books) => $books->withoutGlobalScope('excludesOneRow')->orderBy('id'),
            'books.author.books',
        ])
        ->find($author->id)
        ->books
        ->map(fn (Book $book) => $book->author->books->pluck('id')->sort()->values()->all())
        ->all();

    $first = $query();
    $excludedId = $excluded->id;

    expect($first)->toBe([[$kept->id, $excluded->id], [$kept->id, $excluded->id]])
        ->and($query())->toBe([[$kept->id], [$kept->id]]);
});

test('a scoped model that eager loads itself by default is served with one cache read', function () {
    $author = Author::factory()->create();
    $limitReached = addScopeFailingPastLimits(AuthorEagerLoadingItself::class, 5, 100);

    [$result, $cacheReads, $queries] = cacheReadsAndQueriesOfTheSecondRun(fn () => (new AuthorEagerLoadingItself)
        ->find($author->id));

    expect($result->namesakes)->toHaveCount(0)
        ->and($cacheReads)->toBe(1)
        ->and($queries)->toBe([])
        ->and($limitReached())->toBeFalse();
});

test('a cache hit whose change check throws still returns the rows the current scope allows', function () {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $excludedId = 0;
    $throwOnce = false;
    Book::addGlobalScope('excludesOneRow', function ($builder) use (&$excludedId, &$throwOnce) {
        if ($throwOnce) {
            $throwOnce = false;

            throw new RuntimeException('The scope failed once.');
        }

        $builder->where('books.id', '!=', $excludedId);
    });
    $query = fn () => (new Author)->with('books')->find($author->id)->books->pluck('id')->sort()->values()->all();

    $first = $query();
    $excludedId = $excluded->id;
    $throwOnce = true;

    expect($first)->toBe([$kept->id, $excluded->id])
        ->and($query())->toBe([$kept->id]);
});

test('an entry written without change detection data is served from the cache and still follows the scope', function (Closure $rewrite) {
    $author = Author::factory()->create();
    $kept = Book::factory()->create(['author_id' => $author->id]);
    $excluded = Book::factory()->create(['author_id' => $author->id]);
    $excludedId = 0;
    Book::addGlobalScope('excludesOneRow', function ($builder) use (&$excludedId) {
        $builder->where('books.id', '!=', $excludedId);
    });
    $query = fn () => (new Author)->with('books')->where('id', $author->id);
    $query()->get();
    rewriteCachedEntry($query(), $rewrite);
    $excludedId = $excluded->id;

    [$result, , $queries] = cacheReadsAndQueriesOfTheSecondRun(fn () => $query()->get());

    expect($result->first()->books->pluck('id')->all())->toBe([$kept->id])
        ->and(queriesAgainstTable($queries, 'authors'))->toBe([]);
})->with([
    'the 13.2 shape' => [fn (array $entry) => ['key' => $entry['key'], 'value' => $entry['value']]],
    'an unreadable fingerprint' => [fn (array $entry) => [...$entry, 'eagerLoadFingerprint' => 'unreadable']],
    'a fingerprint missing its fields' => [fn (array $entry) => [...$entry, 'eagerLoadFingerprint' => []]],
]);

test('a cache hit on a missing model with a scoped eager load reads the cache once', function () {
    Book::addGlobalScope('keepsEveryRow', fn ($builder) => $builder->where('books.id', '!=', 0));

    [$result, $cacheReads] = cacheReadsAndQueriesOfTheSecondRun(fn () => (new Author)
        ->with('books')
        ->find(999999));

    expect($result)->toBeNull()
        ->and($cacheReads)->toBe(1);
});

test('a cache hit on an empty result with a scoped eager load reads the cache once', function () {
    Book::addGlobalScope('keepsEveryRow', fn ($builder) => $builder->where('books.id', '!=', 0));

    [$result, $cacheReads] = cacheReadsAndQueriesOfTheSecondRun(fn () => (new Author)
        ->with('books')
        ->where('id', 999999)
        ->get());

    expect($result)->toHaveCount(0)
        ->and($cacheReads)->toBe(1);
});

test('a cached has many through query with an unchanged scope on its eager load reads the cache once', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);
    Book::addGlobalScope('keepsEveryRow', fn ($builder) => $builder->where('books.id', '!=', 0));

    [$result, $cacheReads, $queries] = cacheReadsAndQueriesOfTheSecondRun(fn () => $author->printers()
        ->with('book')
        ->get());

    expect($result->first()->book->id)->toBe($book->id)
        ->and($cacheReads)->toBe(1)
        ->and($queries)->toBe([]);
});

test('a cached has many through query follows a changed scope on its eager load', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Book::class, fn () => [$author->printers()
        ->with('book')
        ->get()
        ->first()
        ->book
        ?->id]);

    expect($first)->toBe([$book->id])
        ->and($second)->toBe([]);
});
