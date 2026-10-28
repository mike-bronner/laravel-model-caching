<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;

function firstPostCacheEntry(): mixed
{
    $prefix = "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:";

    return test()->cache()
        ->tags([
            "{$prefix}genealabslaravelmodelcachingtestsfixturespost",
            "{$prefix}posts",
        ])
        ->get(sha1("{$prefix}posts:genealabslaravelmodelcachingtestsfixturespost-first"));
}

function expectWriteToFlushFirstPost(Closure $write): mixed
{
    (new Post)->first();
    expect(firstPostCacheEntry())->not->toBeNull();

    $result = $write();

    expect(firstPostCacheEntry())->toBeNull();

    return $result;
}

function morphChildAttributes(mixed $type, mixed $id): array
{
    return [
        "description" => "morph type cast",
        "subject" => "morph type cast",
        "commentable_id" => $id,
        "commentable_type" => $type,
    ];
}
