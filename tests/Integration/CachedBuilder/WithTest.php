<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class WithTest extends IntegrationTestCase
{
    public function testWithLimitedQuery()
    {
        $authors = (new Author)
            ->where("id", 1)
            ->with([
                'books' => function ($query) {
                    $query->where("id", "<", 100)
                        ->offset(5)
                        ->limit(1);
                }
            ])
            ->first();
        $uncachedAuthor = (new UncachedAuthor)->with([
                'books' => function ($query) {
                    $query->where("id", "<", 100)
                        ->offset(5)
                        ->limit(1);
                }
            ])
            ->first();

        $this->assertEquals($uncachedAuthor->books()->pluck("id"), $authors->books()->pluck("id"));
        $this->assertEquals($uncachedAuthor->id, $authors->id);
    }

    public function testWithQuery()
    {
        $author = (new Author)
            ->where("id", 1)
            ->with([
                'books' => function ($query) {
                    $query->where("id", "<", 100);
                }
            ])
            ->first();
        $uncachedAuthor = (new UncachedAuthor)->with([
                'books' => function ($query) {
                    $query->where("id", "<", 100);
                },
            ])
            ->where("id", 1)
            ->first();

        $this->assertEquals($uncachedAuthor->books()->count(), $author->books()->count());
        $this->assertEquals($uncachedAuthor->id, $author->id);
    }

    public function testMultiLevelWithQuery()
    {
        $author = (new Author)
            ->where("id", 1)
            ->with([
                'books.publisher' => function ($query) {
                    $query->where("id", "<", 100);
                }
            ])
            ->first();
        $uncachedAuthor = (new UncachedAuthor)->with([
                'books.publisher' => function ($query) {
                    $query->where("id", "<", 100);
                },
            ])
            ->where("id", 1)
            ->first();

        $this->assertEquals($uncachedAuthor->books()->count(), $author->books()->count());
        $this->assertEquals($uncachedAuthor->id, $author->id);
    }

    public function testWithBelongsToManyRelationshipQuery()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.id_=_3-testing:{$this->testingSqlitePath}testing.sqlite:stores-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $stores = (new Book)
            ->with("stores")
            ->find(3)
            ->stores;
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value']
            ->stores;
        $liveResults = (new UncachedBook)
            ->with("stores")
            ->find(3)
            ->stores;

        $this->assertEquals($liveResults->pluck("id"), $stores->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }
}
