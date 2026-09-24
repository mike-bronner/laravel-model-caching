<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOrder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\IntegerStatus;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class MacroKeyArgumentTest extends IntegrationTestCase
{
    public function testWrappedBuilderMethodTakesAnArrayArgument()
    {
        $names = (new UncachedAuthor)->orderBy("id")->limit(3)->pluck("name");

        $first = AuthorWithFilteringBuilder::query()->whereNameIn([$names[0]])->pluck("name");
        $second = AuthorWithFilteringBuilder::query()->whereNameIn([$names[1], $names[2]])->pluck("name");

        $this->assertEquals([$names[0]], $first->all());
        $this->assertEqualsCanonicalizing([$names[1], $names[2]], $second->all());
    }

    public function testWrappedBuilderMethodTakesAClosureArgument()
    {
        $authors = AuthorWithFilteringBuilder::query()
            ->applyCallback(fn ($query) => $query->where("id", 1))
            ->pluck("id");

        $this->assertEquals([1], $authors->all());
    }

    public function testWrappedBuilderMethodTakesABackedEnumArgument()
    {
        (new UncachedAuthor)->where("id", 1)->update(["is_famous" => true]);
        (new UncachedAuthor)->where("id", "!=", 1)->update(["is_famous" => false]);

        $famous = AuthorWithFilteringBuilder::query()->whereFamousStatus(IntegerStatus::Active)->pluck("id");
        $notFamous = AuthorWithFilteringBuilder::query()->whereFamousStatus(IntegerStatus::Inactive)->pluck("id");

        $this->assertEquals([1], $famous->all());
        $this->assertNotContains(1, $notFamous->all());
        $this->assertNotEmpty($notFamous->all());
    }

    public function testWrappedBuilderMethodTakesAUnitEnumArgument()
    {
        $newest = AuthorWithFilteringBuilder::query()->orderedBy(AuthorOrder::Newest)->pluck("id");
        $oldest = AuthorWithFilteringBuilder::query()->orderedBy(AuthorOrder::Oldest)->pluck("id");

        $this->assertEquals($oldest->reverse()->values()->all(), $newest->all());
        $this->assertNotEquals($oldest->all(), $newest->all());
    }

    public function testEnumArgumentsAreSpelledByValueOrName()
    {
        $method = new \ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

        $this->assertSame(
            "1_Newest",
            $method->invoke(Book::query(), [IntegerStatus::Active, AuthorOrder::Newest]),
        );
    }

    public function testLocalMacroTakesAnArrayArgument()
    {
        $idIn =fn ($builder, array $ids) => $builder->whereIn("id", $ids);

        $first = Book::query();
        $first->macro("idIn", $idIn);
        $second = Book::query();
        $second->macro("idIn", $idIn);

        $this->assertEquals([1], $first->idIn([1])->pluck("id")->all());
        $this->assertEquals([2], $second->idIn([2])->pluck("id")->all());
    }

    public function testWriteDeclaredOnWrappedBuilderInvalidatesCache()
    {
        $before = (new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name");

        AuthorWithFilteringBuilder::query()->updateOrInsert(["id" => 1], ["name" => "written by inner builder"]);

        $live = (new UncachedAuthor)->orderBy("id")->pluck("name");

        $this->assertNotEquals($before, $live);
        $this->assertEquals($live, (new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name"));
    }

    public function testJoinableArgumentsKeepTheirKeySpelling()
    {
        $stringable = new class {
            public function __toString(): string
            {
                return "stringable";
            }
        };
        $arguments = ["text", 7, 2.5, true, false, null, $stringable];
        $method = new \ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

        $this->assertSame(implode("_", $arguments), $method->invoke(Book::query(), $arguments));
    }
}
