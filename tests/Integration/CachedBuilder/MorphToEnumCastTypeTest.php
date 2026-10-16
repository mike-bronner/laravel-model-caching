<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

enum CommentableTypeEnum: string
{
    case Post = 'post';
}

class CommentWithEnumCastType extends Model
{
    use Cachable;

    protected $table = 'comments';

    protected $guarded = [];

    protected $casts = [
        'commentable_type' => CommentableTypeEnum::class,
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

afterEach(function () {
    Relation::morphMap([], false);
});

test('deleting a child whose morph type column is cast to an enum invalidates the parent cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    $comment = CommentWithEnumCastType::create([
        'description' => 'enum cast',
        'subject' => 'enum cast',
        'commentable_id' => $post->id,
        'commentable_type' => CommentableTypeEnum::Post,
    ]);

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    (new Post)->first();
    expect($this->cache()->tags($tags)->get($key))->not->toBeNull();

    $comment->delete();

    expect($this->cache()->tags($tags)->get($key))->toBeNull(
        'Post cache should be invalidated after deleting a child with an enum-cast morph type.',
    );
});

test('eager loading a morph to whose type column is cast to an enum works on a warm cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    CommentWithEnumCastType::create([
        'description' => 'enum cast',
        'subject' => 'enum cast',
        'commentable_id' => $post->id,
        'commentable_type' => CommentableTypeEnum::Post,
    ]);

    CommentWithEnumCastType::with('commentable')->where('subject', 'enum cast')->get();
    $warm = CommentWithEnumCastType::with('commentable')->where('subject', 'enum cast')->get();

    expect($warm->first()->commentable)->toBeInstanceOf(Post::class);
});
