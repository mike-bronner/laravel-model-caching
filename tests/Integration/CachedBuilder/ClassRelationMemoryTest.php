<?php

use GeneaLabs\LaravelModelCaching\RelationResolver;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithRuntimeCacheState;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithAttributeDependentRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithFixableRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithPrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithRuntimeCacheStateAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithRuntimeRelations;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithRuntimeRelationsChild;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithStorePrefixedPivot;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\EagerLoadCountingAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\EagerLoadCountingBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PostWithTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RelationCallLog;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RuntimeRelationAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\TenantAuthorWithTtl;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    Relation::morphMap([], false);
    BookWithFixableRelation::$broken = true;
    AuthorWithRuntimeCacheState::$runtimeCachePrefix = "first";
    AuthorWithRuntimeCacheState::$runtimeTtl = null;
    DB::purge("testing");
});

test('a second read of the same class and paths builds its key, tags and ttl without calling a relation method', function () {
    $read = fn () => cacheKeyTagsAndTtl(
        (new EagerLoadCountingBook)->newQuery()->with(["author.books", "stores", "publisher"]),
    );

    RelationCallLog::reset();
    $first = $read();
    $firstCalls = RelationCallLog::$calls;
    RelationCallLog::reset();
    $second = $read();

    expect(relationCallsOn($firstCalls, EagerLoadCountingBook::class, "author"))->not->toBeEmpty();
    expect(relationCallsOn($firstCalls, EagerLoadCountingAuthor::class, "books"))->not->toBeEmpty();
    expect(relationCallsOn($firstCalls, EagerLoadCountingBook::class, "stores"))->not->toBeEmpty();
    expect(relationCallsOn($firstCalls, EagerLoadCountingBook::class, "publisher"))->not->toBeEmpty();
    expect(RelationCallLog::$calls->count())->toBe(0);
    expect($second)->toBe($first);
});

test('the config cache prefix is read on every read', function () {
    $read = fn () => cacheKeyTagsAndTtl(Book::with("author"));

    $first = $read();
    config(["laravel-model-caching.cache-prefix" => "changed:"]);
    $second = $read();

    expect($first["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturesauthor"));
    expect($second["tags"])->toContain(tag("changed:genealabslaravelmodelcachingtestsfixturesauthor"));
    expect($second["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixturesauthor"));
});

test('the cache prefix of the related model is read on every read', function () {
    $read = fn () => cacheKeyTagsAndTtl(BookWithRuntimeCacheStateAuthor::with("author"));

    $first = $read();
    AuthorWithRuntimeCacheState::$runtimeCachePrefix = "second";
    $second = $read();

    expect($first["tags"])->toContain(tag("first:genealabslaravelmodelcachingtestsfixturesauthorwithruntimecachestate"));
    expect($second["tags"])->toContain(tag("second:genealabslaravelmodelcachingtestsfixturesauthorwithruntimecachestate"));
    expect($second["tags"])->not->toContain(tag("first:genealabslaravelmodelcachingtestsfixturesauthorwithruntimecachestate"));
});

test('the connection name of the related model is read on every read', function () {
    useTenantConnection();
    $database = DB::connection("testing")->getDatabaseName();

    $first = cacheKeyTagsAndTtl(Book::with("author"));
    $second = cacheKeyTagsAndTtl(Book::on("tenant")->with("author"));
    $third = cacheKeyTagsAndTtl(Book::with("author"));

    expect($first["key"])->toContain("-testing:{$database}:author");
    expect($second["key"])->toContain("-tenant:{$database}:author");
    expect($second["key"])->not->toContain("-testing:{$database}:author");
    expect($third["key"])->toContain("-testing:{$database}:author");
    expect($third["key"])->not->toContain("-tenant:{$database}:author");
});

test('the database name of the related model is read on every read', function () {
    $read = fn () => cacheKeyTagsAndTtl(Book::with("author"));
    $database = DB::connection("testing")->getDatabaseName();

    $first = $read();
    DB::connection("testing")->setDatabaseName("renamed.sqlite");
    $second = $read();

    expect($first["key"])->toContain("-testing:{$database}:author");
    expect($second["key"])->toContain("-testing:renamed.sqlite:author");
    expect($second["key"])->not->toContain("-testing:{$database}:author");
    expect($second["tags"])->toContain(
        "genealabs:laravel-model-caching:testing:renamed.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
    );
});

test('the config ttl is read on every read', function () {
    $read = fn () => cacheKeyTagsAndTtl(Book::with("author"));

    config(["laravel-model-caching.ttl" => 40]);
    $first = $read();
    config(["laravel-model-caching.ttl" => 50]);
    $second = $read();

    expect($first["ttl"])->toBe(40);
    expect($second["ttl"])->toBe(50);
});

test('the ttl of the related model is read on every read', function () {
    $read = fn () => cacheKeyTagsAndTtl(BookWithRuntimeCacheStateAuthor::with("author"));

    AuthorWithRuntimeCacheState::$runtimeTtl = 30;
    $first = $read();
    AuthorWithRuntimeCacheState::$runtimeTtl = 15;
    $second = $read();

    expect($first["ttl"])->toBe(30);
    expect($second["ttl"])->toBe(15);
});

test('an eager load keeps the key, tags and ttl it had before the class memory, on every read', function (Closure $query, Closure $expected) {
    foreach (range(1, 3) as $read) {
        expect(cacheKeyTagsAndTtl($query()))->toBe($expected());
    }
})->with([
    "a single relation" => [
        fn () => Book::with("author"),
        fn () => [
            "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
                . relatedConnectionSegment("author"),
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbook"),
                tag("genealabslaravelmodelcachingtestsfixturesauthor"),
                tag("books"),
            ],
            "ttl" => null,
        ],
    ],
    "a nested dot path" => [
        fn () => Book::with("author.books"),
        fn () => [
            "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
                . relatedConnectionSegment("author")
                . "-author.books",
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbook"),
                tag("genealabslaravelmodelcachingtestsfixturesauthor"),
                tag("books"),
            ],
            "ttl" => null,
        ],
    ],
    "a belongs to many with a custom pivot class" => [
        fn () => BookWithStorePrefixedPivot::with("stores"),
        fn () => [
            "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbookwithstoreprefixedpivot"
                . relatedConnectionSegment("stores"),
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbookwithstoreprefixedpivot"),
                tag("genealabslaravelmodelcachingtestsfixturesstore"),
                tag("books"),
                tag("store-prefix:book-store"),
                tag("book-store"),
            ],
            "ttl" => null,
        ],
    ],
    "a has many through" => [
        fn () => Author::with("printers"),
        fn () => [
            "key" => tagPrefix() . "authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
                . relatedConnectionSegment("printers"),
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesauthor"),
                tag("genealabslaravelmodelcachingtestsfixturesprinter"),
                tag("authors"),
            ],
            "ttl" => null,
        ],
    ],
    "a has one through" => [
        fn () => Supplier::with("history"),
        fn () => [
            "key" => tagPrefix() . "suppliers:genealabslaravelmodelcachingtestsfixturessupplier"
                . relatedConnectionSegment("history"),
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturessupplier"),
                tag("genealabslaravelmodelcachingtestsfixtureshistory"),
                tag("suppliers"),
            ],
            "ttl" => null,
        ],
    ],
    "a name that is not a relation" => [
        fn () => Book::with("notARelation"),
        fn () => [
            "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook-notARelation",
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbook"),
                tag("books"),
            ],
            "ttl" => null,
        ],
    ],
    "a nested path whose second segment is not a relation" => [
        fn () => Book::with("author.notARelation"),
        fn () => [
            "key" => "",
            "tags" => [
                tag("genealabslaravelmodelcachingtestsfixturesbook"),
                tag("genealabslaravelmodelcachingtestsfixturesauthor"),
                tag("books"),
            ],
            "ttl" => null,
        ],
    ],
]);

function freshInstanceDependentRelation(): array
{
    return [
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbookwithattributedependentrelation"
            . relatedConnectionSegment("dependent"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbookwithattributedependentrelation"),
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("books"),
        ],
        "ttl" => null,
    ];
}

function hydratedBookPickingTheTenantAuthor(): BookWithAttributeDependentRelation
{
    useTenantConnection();
    $book = BookWithAttributeDependentRelation::first()
        ->setAttribute("use_tenant_author", true);

    expect($book->dependent()->getRelated())->toBeInstanceOf(TenantAuthorWithTtl::class);

    return $book;
}

test('an attribute dependent relation names the class a fresh instance resolves when the hydrated read comes first', function () {
    $book = hydratedBookPickingTheTenantAuthor();

    expect(cacheKeyTagsAndTtl($book->newQuery()->with("dependent")))->toBe(freshInstanceDependentRelation());
    expect(cacheKeyTagsAndTtl(BookWithAttributeDependentRelation::with("dependent")))->toBe(freshInstanceDependentRelation());
    expect(cacheKeyTagsAndTtl($book->newQuery()->with("dependent")))->toBe(freshInstanceDependentRelation());
});

test('an attribute dependent relation names the class a fresh instance resolves when the fresh read comes first', function () {
    $book = hydratedBookPickingTheTenantAuthor();

    expect(cacheKeyTagsAndTtl(BookWithAttributeDependentRelation::with("dependent")))->toBe(freshInstanceDependentRelation());
    expect(cacheKeyTagsAndTtl($book->newQuery()->with("dependent")))->toBe(freshInstanceDependentRelation());
    expect(cacheKeyTagsAndTtl(BookWithAttributeDependentRelation::with("dependent")))->toBe(freshInstanceDependentRelation());
});

test('a path with a morph to at any depth reads the morph map on every read', function (Closure $query) {
    Relation::morphMap(["post" => Post::class], false);
    $first = cacheKeyTagsAndTtl($query());
    Relation::morphMap(["post" => PostWithTtl::class], false);
    $second = cacheKeyTagsAndTtl($query());

    expect($first["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturespost"));
    expect($second["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturespostwithttl"));
    expect($second["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixturespost"));
    expect($second["ttl"])->toBe(30);
})->with([
    "as the first segment" => [fn () => Comment::with("commentable")],
    "as a middle segment" => [fn () => Book::with("comments.commentable.comments")],
    "as the last segment" => [fn () => Book::with("comments.commentable")],
]);

test('a path read before its name was a relation is resolved once the relation is registered', function () {
    $read = fn () => cacheKeyTagsAndTtl(BookWithRuntimeRelations::with(["lateTop", "author.lateNested"]));

    $before = $read();
    BookWithRuntimeRelations::resolveRelationUsing(
        "lateTop",
        fn (BookWithRuntimeRelations $book) => $book->belongsTo(Publisher::class, "publisher_id"),
    );
    RuntimeRelationAuthor::resolveRelationUsing(
        "lateNested",
        fn (RuntimeRelationAuthor $author) => $author->hasMany(EagerLoadCountingBook::class, "author_id"),
    );
    $after = $read();

    expect($before["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
    expect($before["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixtureseagerloadcountingbook"));
    expect($after["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
    expect($after["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixtureseagerloadcountingbook"));
});

test('a replaced runtime relation resolver is seen on the next read', function () {
    $read = fn () => cacheKeyTagsAndTtl(BookWithRuntimeRelations::with(["replacedTop", "author.replacedNested"]));
    BookWithRuntimeRelations::resolveRelationUsing(
        "replacedTop",
        fn (BookWithRuntimeRelations $book) => $book->belongsTo(Publisher::class, "publisher_id"),
    );
    RuntimeRelationAuthor::resolveRelationUsing(
        "replacedNested",
        fn (RuntimeRelationAuthor $author) => $author->hasMany(Book::class, "author_id"),
    );

    $before = $read();
    BookWithRuntimeRelations::resolveRelationUsing(
        "replacedTop",
        fn (BookWithRuntimeRelations $book) => $book->belongsTo(Author::class, "author_id"),
    );
    RuntimeRelationAuthor::resolveRelationUsing(
        "replacedNested",
        fn (RuntimeRelationAuthor $author) => $author->hasMany(EagerLoadCountingBook::class, "author_id"),
    );
    $after = $read();

    expect($before["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
    expect($before["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixtureseagerloadcountingbook"));
    expect($after["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
    expect($after["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturesauthor"));
    expect($after["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixtureseagerloadcountingbook"));
});

test('a runtime relation registered on a parent class is seen by a subclass read before it', function () {
    $read = fn () => cacheKeyTagsAndTtl(BookWithRuntimeRelationsChild::with("inheritedRuntime"));

    $before = $read();
    BookWithRuntimeRelations::resolveRelationUsing(
        "inheritedRuntime",
        fn (BookWithRuntimeRelations $book) => $book->belongsTo(Publisher::class, "publisher_id"),
    );
    $after = $read();

    expect($before["tags"])->not->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
    expect($after["tags"])->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
});

test('model classes and subclasses keep their own remembered relations', function () {
    $queries = [
        fn () => Book::with("author"),
        fn () => EagerLoadCountingBook::with("author"),
        fn () => BookWithPrefixedAuthor::with("author"),
    ];
    $alone = array_map(keyTagsAndTtlFromEmptyMemory(...), $queries);

    $interleaved = array_map(fn (Closure $query): array => cacheKeyTagsAndTtl($query()), $queries);
    $again = array_map(fn (Closure $query): array => cacheKeyTagsAndTtl($query()), array_reverse($queries));

    expect(collect($alone)->pluck("tags")->unique()->count())->toBe(3);
    expect(collect($alone)->pluck("key")->unique()->count())->toBe(3);
    expect($interleaved)->toBe($alone);
    expect(array_reverse($again))->toBe($alone);
});

test('a constraint closure runs on every read and keys its sql', function () {
    $counter = (object) ["runs" => 0];
    $constrained = fn (string $name) => function ($query) use ($name, $counter) {
        $counter->runs++;
        $query->where("name", $name);
    };

    $first = cacheKeyTagsAndTtl(Book::with(["author" => $constrained("A")]));
    $runsAfterFirst = $counter->runs;
    $same = cacheKeyTagsAndTtl(Book::with(["author" => $constrained("A")]));
    $other = cacheKeyTagsAndTtl(Book::with(["author" => $constrained("B")]));

    expect($runsAfterFirst)->toBeGreaterThan(0);
    expect($counter->runs)->toBe($runsAfterFirst * 3);
    expect($same["key"])->toBe($first["key"]);
    expect($other["key"])->not->toBe($first["key"]);
});

test('a read with a constraint closure leaves a later read without one as if the closure never ran', function () {
    $plain = fn () => Book::with(["author", "author.books"]);
    $neverConstrained = keyTagsAndTtlFromEmptyMemory($plain);

    cacheKeyTagsAndTtl(Book::with([
        "author" => fn ($query) => $query->where("name", "A"),
        "author.books" => fn ($query) => $query->where("title", "B"),
    ]));

    expect(cacheKeyTagsAndTtl($plain()))->toBe($neverConstrained);
});

test('a relation method that throws gives the same exception and leaves nothing remembered', function () {
    $query = fn () => BookWithFixableRelation::with("fixable");

    expect(fn () => cacheKeyTagsAndTtl($query()))
        ->toThrow(RuntimeException::class, "The fixable relation is broken.");
    expect(fn () => (new ReflectionMethod($query(), "makeCacheTags"))->invoke($query()))
        ->toThrow(RuntimeException::class, "The fixable relation is broken.");
    expect(fn () => $query()->get())
        ->toThrow(RuntimeException::class, "The fixable relation is broken.");
    expect(rememberedRelationPaths()[BookWithFixableRelation::class] ?? [])->not->toHaveKey("fixable");

    BookWithFixableRelation::$broken = false;

    expect(cacheKeyTagsAndTtl($query())["tags"])
        ->toContain(tag("genealabslaravelmodelcachingtestsfixturespublisher"));
});

test('the remembered relations grow only with distinct class and path pairs, and can be cleared', function () {
    $read = fn () => cacheKeyTagsAndTtl(Book::with(["author.books", "publisher"]));

    expect(rememberedRelationPathCount())->toBe(0);

    $read();
    $afterOneRead = rememberedRelationPathCount();

    foreach (range(2, 1000) as $read_) {
        $read();
    }

    expect($afterOneRead)->toBeGreaterThan(0);
    expect(rememberedRelationPathCount())->toBe($afterOneRead);

    cacheKeyTagsAndTtl(Author::with("books"));

    expect(rememberedRelationPathCount())->toBe($afterOneRead + 1);

    RelationResolver::forgetClassRelationPaths();

    expect(rememberedRelationPathCount())->toBe(0);
});
