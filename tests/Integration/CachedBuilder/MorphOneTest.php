<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class MorphOneTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        (new Book)
            ->get()
            ->each(function ($book) {
                $book->image()
                    ->create([
                        "path" => $this->faker->url(),
                    ]);
            });
        $this->cache()->flush();
    }

    public function testMorphTo()
    {
        $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_=_1-testing:{$this->testingSqlitePath}testing.sqlite:image");
        $key2 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_=_4-testing:{$this->testingSqlitePath}testing.sqlite:image");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesimage",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $books1 = (new Book)
            ->with("image")
            ->where("author_id", 1)
            ->get();
        $cachedResults1 = $this->cache()
            ->tags($tags)
            ->get($key1)['value'];
        $books2 = (new Book)
            ->with("image")
            ->where("author_id", 4)
            ->get();
        $cachedResults2 = $this->cache()
            ->tags($tags)
            ->get($key2)['value'];

        $this->assertEquals($cachedResults1->pluck("image.id"), $books1->pluck("image.id"));
        $this->assertEquals($cachedResults2->pluck("image.id"), $books2->pluck("image.id"));
        $this->assertNotEquals($cachedResults1->pluck("image.id"), $cachedResults2->pluck("image.id"));
        $this->assertNotEquals($books1->pluck("image.id"), $books2->pluck("image.id"));
        $this->assertNotNull($books1->first()->image);
        $this->assertNotNull($books2->first()->image);
        $this->assertNotNull($cachedResults1->first()->image);
        $this->assertNotNull($cachedResults2->first()->image);
    }
}
