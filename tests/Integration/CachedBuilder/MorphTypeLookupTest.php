<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;

class MorphTypeLookupTest extends IntegrationTestCase
{
    private function seedComments() : void
    {
        Comment::create([
            'commentable_id' => (new Post)->first()->id,
            'commentable_type' => Post::class,
            'description' => 'Comment on post',
            'subject' => 'Post comment',
        ]);
        Comment::create([
            'commentable_id' => (new Book)->first()->id,
            'commentable_type' => Book::class,
            'description' => 'Comment on book',
            'subject' => 'Book comment',
        ]);
    }

    private function morphTypeLookupCount() : int
    {
        return collect(DB::getQueryLog())
            ->filter(function (array $query) {
                return str_contains($query["query"], 'distinct "commentable_type"');
            })
            ->count();
    }

    public function testMorphTypeLookupIsNotRepeatedForEveryTagGeneration() : void
    {
        $this->seedComments();
        $this->cache()->flush();

        DB::enableQueryLog();

        (new Comment)->with("commentable")->get();
        (new Comment)->with("commentable")->get();
        (new Comment)->with("commentable")->get();

        $this->assertSame(
            1,
            $this->morphTypeLookupCount(),
            "Tags are generated for every cached query touching the relation, so the type lookup has to be answered from cache after the first."
        );
    }

    public function testMorphTypeLookupIsRepeatedAfterTheTableIsWrittenTo() : void
    {
        $this->seedComments();
        $this->cache()->flush();

        (new Comment)->with("commentable")->get();

        DB::enableQueryLog();
        (new Comment)->with("commentable")->get();

        $this->assertSame(
            0,
            $this->morphTypeLookupCount(),
            "The memo is warm, so nothing should reach the database for it."
        );

        Comment::create([
            'commentable_id' => (new Author)->first()->id,
            'commentable_type' => Author::class,
            'description' => 'Comment on author',
            'subject' => 'Author comment',
        ]);

        DB::flushQueryLog();
        (new Comment)->with("commentable")->get();

        $this->assertSame(
            1,
            $this->morphTypeLookupCount(),
            "A new morph type can only arrive through a write to this table, and that write has to drop the memo."
        );
    }
}
