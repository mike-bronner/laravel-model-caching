<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedPost;
use Illuminate\Support\Facades\DB;

test('the morph type memo picks up a type written outside the models once its ttl has passed', function () {
    config([
        "cache.stores.morph-memo" => ["driver" => "array"],
        "laravel-model-caching.store" => "morph-memo",
    ]);
    insertMorphMemoComment(Post::class, (new Post)->first()->id);
    $query = (new Comment)->with("commentable");

    expect(morphMemoCacheTags($query))->not->toContain(bookTags()[0]);

    insertMorphMemoComment(Book::class, (new Book)->first()->id);
    $this->travel(30)->seconds();

    expect(morphMemoCacheTags($query))->not->toContain(bookTags()[0]);

    $this->travel(31)->seconds();

    expect(morphMemoCacheTags($query))->toContain(bookTags()[0]);
});

test('a nested eager load and a direct query share the memo of the model owning the morph column', function () {
    insertMorphMemoComment(Post::class, (new Post)->first()->id);
    $this->cache()->flush();
    DB::enableQueryLog();

    (new PrefixedPost)->with("comments.commentable")->get();
    (new Comment)->with("commentable")->get();

    $lookups = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query["query"], 'distinct "commentable_type"'));
    $memo = ModelCacheRepository::make()->get(
        morphMemoPrefix() . "morph-types:comments:commentable_type",
        [morphMemoPrefix() . "comments"],
    );

    expect($lookups)->toHaveCount(1)
        ->and($memo)->toContain(Post::class);
});

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
