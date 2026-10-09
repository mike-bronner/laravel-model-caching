<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorBeginsWithScoped;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithInlineGlobalScope;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthorWithInlineGlobalScope;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('scope clause parsing', function () {
    $author = Author::factory()->count(1)
        ->create(['name' => 'Anton'])
        ->first();
    $authors = (new Author)
        ->startsWithA()
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_like_A%25-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->startsWithA()
        ->get();

    expect($authors->contains($author))->toBeTrue();
    expect($cachedResults->contains($author))->toBeTrue();
    expect($liveResults->contains($author))->toBeTrue();
});

test('scope clause with parameter', function () {
    $author = Author::factory()->count(1)
        ->create(['name' => 'Boris'])
        ->first();
    $authors = (new Author)
        ->nameStartsWith("B")
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_like_B%25-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->nameStartsWith("B")
        ->get();

    expect($authors->contains($author))->toBeTrue();
    expect($cachedResults->contains($author))->toBeTrue();
    expect($liveResults->contains($author))->toBeTrue();
});

test('global scopes are cached', function () {
    $user = User::factory()->create(["name" => "Abernathy Kings"]);
    $this->actingAs($user);
    $author = UncachedAuthor::factory()->count(1)
        ->create(['name' => 'Alois'])
        ->first();
    $authors = (new AuthorBeginsWithScoped)
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthorbeginswithscoped-name_like_A%250=A%25");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthorbeginswithscoped",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->nameStartsWith("A")
        ->get();

    expect($authors->contains($author))->toBeTrue();
    expect($cachedResults->contains($author))->toBeTrue();
    expect($liveResults->contains($author))->toBeTrue();
});

test('inline global scopes are cached', function () {
    $author = UncachedAuthor::factory()->count(1)
        ->create(['name' => 'Alois'])
        ->first();
    $authors = (new AuthorWithInlineGlobalScope)
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthorwithinlineglobalscope-authors.deleted_at_null-name_like_A%250=A%25");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthorwithinlineglobalscope",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthorWithInlineGlobalScope)
        ->get();

    expect($authors->contains($author))->toBeTrue();
    expect($cachedResults->contains($author))->toBeTrue();
    expect($liveResults->contains($author))->toBeTrue();
});

test('global scopes when switching context using all method', function () {
    Author::factory()->count(200)->create();
    $user = User::factory()->create(["name" => "Andrew Junior"]);
    $this->actingAs($user);
    $authorsA = (new AuthorBeginsWithScoped)
        ->all()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();
    $user = User::factory()->create(["name" => "Barry Barry Barry"]);
    $this->actingAs($user);
    $authorsB = (new AuthorBeginsWithScoped)
        ->all()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();

    expect($authorsA)->toHaveCount(1);
    expect($authorsB)->toHaveCount(1);
    expect($authorsA->first())->toEqual("A");
    expect($authorsB->first())->toEqual("B");
});

test('global scopes when switching context using get method', function () {
    Author::factory()->count(200)->create();
    $user = User::factory()->create(["name" => "Anton Junior"]);
    $this->actingAs($user);
    $authorsA = (new AuthorBeginsWithScoped)
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();
    $user = User::factory()->create(["name" => "Burli Burli Burli"]);
    $this->actingAs($user);
    $authorsB = (new AuthorBeginsWithScoped)
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();

    expect($authorsA)->toHaveCount(1);
    expect($authorsB)->toHaveCount(1);
    expect($authorsA->first())->toEqual("A");
    expect($authorsB->first())->toEqual("B");
});

test('global scopes are not cached when using without global scopes', function () {
    $user = User::factory()->create(["name" => "Abernathy Kings"]);
    $this->actingAs($user);
    $author = UncachedAuthor::factory()->count(1)
        ->create(['name' => 'Alois'])
        ->first();
    $authors = (new AuthorBeginsWithScoped)
        ->withoutGlobalScopes()
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthorbeginswithscoped");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthorbeginswithscoped",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->nameStartsWith("A")
        ->get();

    expect($authors->contains($author))->toBeTrue();
    expect($cachedResults->contains($author))->toBeTrue();
    expect($liveResults->contains($author))->toBeTrue();
});

test('without global scopes', function () {
    Author::factory()->count(200)->create();
    $user = User::factory()->create(["name" => "Andrew Junior"]);
    $this->actingAs($user);
    $authorsA = (new AuthorBeginsWithScoped)
        ->withoutGlobalScopes()
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();
    $user = User::factory()->create(["name" => "Barry Barry Barry"]);
    $this->actingAs($user);
    $authorsB = (new AuthorBeginsWithScoped)
        ->withoutGlobalScopes(['GeneaLabs\LaravelModelCaching\Tests\Fixtures\Scopes\NameBeginsWith'])
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();

    expect(count($authorsA))->toBeGreaterThan(1);
    expect(count($authorsB))->toBeGreaterThan(1);
});

test('without global scope', function () {
    Author::factory()->count(200)->create();
    $user = User::factory()->create(["name" => "Andrew Junior"]);
    $this->actingAs($user);
    $authorsA = (new AuthorBeginsWithScoped)
        ->withoutGlobalScope('GeneaLabs\LaravelModelCaching\Tests\Fixtures\Scopes\NameBeginsWith')
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();
    $user = User::factory()->create(["name" => "Barry Barry Barry"]);
    $this->actingAs($user);
    $authorsB = (new AuthorBeginsWithScoped)
        ->withoutGlobalScope('GeneaLabs\LaravelModelCaching\Tests\Fixtures\Scopes\NameBeginsWith')
        ->get()
        ->map(function ($author) {
            return (new Str)->substr($author->name, 0, 1);
        })
        ->unique();

    expect(count($authorsA))->toBeGreaterThan(1);
    expect(count($authorsB))->toBeGreaterThan(1);
});

test('local scopes in relationship', function () {
    $author = Author::factory()->create();
    Book::factory()->create(['author_id' => $author->id, 'title' => 'Alpha Book']);
    Book::factory()->create(['author_id' => $author->id, 'title' => 'Beta Book']);

    $first = "A";
    $second = "B";
    $authors1 = (new Author)
        ->with(['books' => static function (HasMany $model) use ($first) {
            $model->startsWith($first);
        }])
        ->get();
    $authors2 = (new Author)
        ->with(['books' => static function (HasMany $model) use ($second) {
            $model->startsWith($second);
        }])
        ->get();

    $booksFromAuthors1 = $authors1->find($author->id)->books;
    $booksFromAuthors2 = $authors2->find($author->id)->books;

    expect($booksFromAuthors1)->toHaveCount(1);
    expect($booksFromAuthors2)->toHaveCount(1);
    expect($booksFromAuthors1->first()->title)->toEqual('Alpha Book');
    expect($booksFromAuthors2->first()->title)->toEqual('Beta Book');
});

test('scope not applied twice', function () {
    $user = User::factory()->create(["name" => "Anton Junior"]);
    $this->actingAs($user);
    DB::enableQueryLog();

    (new AuthorBeginsWithScoped)
        ->get();
    $queryLog = DB::getQueryLog();

    expect($queryLog)->toHaveCount(1);
    expect($queryLog[0]['bindings'])->toHaveCount(
        1,
        "There should only be 1 binding, scope is being applied more than once.",
    );
});
