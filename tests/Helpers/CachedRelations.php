<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function cachedRelations(): array
{
    return [
        CachedBelongsToMany::class => (new Book)->first()->stores(),
        CachedMorphToMany::class => (new Post)->first()->tags(),
        CachedHasManyThrough::class => (new Author)->first()->printers(),
        CachedHasOneThrough::class => (new Supplier)->first()->history(),
    ];
}

function getCachePrefix(object $subject): string
{
    return (new ReflectionMethod($subject, "getCachePrefix"))->invoke($subject);
}

function cachedStoresPayload()
{
    $testingSqlitePath = test()->testingSqlitePath;

    $key = sha1("genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:stores",
    ];

    return test()->cache()->tags($tags)->get($key);
}

function bookWithOneStore(array $pivot = []) : array
{
    $book = Book::factory()->create();
    $store = Store::factory()->create();
    $book->stores()->attach($store, $pivot);

    return [$book, $store];
}

function insertMorphMemoComment(string $type, int $id): void
{
    DB::table("comments")->insert([
        "commentable_id" => $id,
        "commentable_type" => $type,
        "description" => "Comment",
        "subject" => "Comment",
    ]);
}

function morphMemoCacheTags($query): array
{
    return (new ReflectionMethod($query, "makeCacheTags"))
        ->invoke($query);
}

function morphMemoPrefix(): string
{
    return "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:";
}

function makeCacheTags(object $subject): array
{
    $method = new ReflectionMethod($subject, 'makeCacheTags');

    return $method->invoke($subject);
}

function assertTagsContainTable(array $tags, string $table): void
{
    $suffix = ':' . (new Str)->slug($table);

    expect(collect($tags)->contains(fn ($tag) => str_ends_with($tag, $suffix)))->toBeTrue("Relation cache tags should contain a tag for the joined table '{$table}'. Got: "
            . implode(', ', $tags));
}
