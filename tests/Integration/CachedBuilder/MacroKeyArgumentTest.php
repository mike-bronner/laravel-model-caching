<?php

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOrder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\IntegerStatus;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('wrapped builder method takes an array argument', function () {
    $names = (new UncachedAuthor)->orderBy("id")->limit(3)->pluck("name");

    $first = AuthorWithFilteringBuilder::query()->whereNameIn([$names[0]])->pluck("name");
    $second = AuthorWithFilteringBuilder::query()->whereNameIn([$names[1], $names[2]])->pluck("name");

    expect($first->all())->toEqual([$names[0]]);
    expect($second->all())->toEqualCanonicalizing([$names[1], $names[2]]);
});

test('wrapped builder method takes a closure argument', function () {
    $authors = AuthorWithFilteringBuilder::query()
        ->applyCallback(fn ($query) => $query->where("id", 1))
        ->pluck("id");

    expect($authors->all())->toEqual([1]);
});

test('wrapped builder method takes a backed enum argument', function () {
    (new UncachedAuthor)->where("id", 1)->update(["is_famous" => true]);
    (new UncachedAuthor)->where("id", "!=", 1)->update(["is_famous" => false]);

    $famous = AuthorWithFilteringBuilder::query()->whereFamousStatus(IntegerStatus::Active)->pluck("id");
    $notFamous = AuthorWithFilteringBuilder::query()->whereFamousStatus(IntegerStatus::Inactive)->pluck("id");

    expect($famous->all())->toEqual([1]);
    expect($notFamous->all())->not->toContain(1);
    expect($notFamous->all())->not->toBeEmpty();
});

test('wrapped builder method takes a unit enum argument', function () {
    $newest = AuthorWithFilteringBuilder::query()->orderedBy(AuthorOrder::Newest)->pluck("id");
    $oldest = AuthorWithFilteringBuilder::query()->orderedBy(AuthorOrder::Oldest)->pluck("id");

    expect($newest->all())->toEqual($oldest->reverse()->values()->all());
    expect($newest->all())->not->toEqual($oldest->all());
});

test('enum arguments are spelled by value or name', function () {
    $method = new \ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

    expect($method->invoke(Book::query(), [IntegerStatus::Active, AuthorOrder::Newest]))->toBe(
        "1_Newest",
    );
});

test('local macro takes an array argument', function () {
    $idIn =fn ($builder, array $ids) => $builder->whereIn("id", $ids);

    $first = Book::query();
    $first->macro("idIn", $idIn);
    $second = Book::query();
    $second->macro("idIn", $idIn);

    expect($first->idIn([1])->pluck("id")->all())->toEqual([1]);
    expect($second->idIn([2])->pluck("id")->all())->toEqual([2]);
});

test('write declared on wrapped builder invalidates cache', function () {
    $before = (new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name");

    AuthorWithFilteringBuilder::query()->updateOrInsert(["id" => 1], ["name" => "written by inner builder"]);

    $live = (new UncachedAuthor)->orderBy("id")->pluck("name");

    expect($live)->not->toEqual($before);
    expect((new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name"))->toEqual($live);
});

test('joinable arguments keep their key spelling', function () {
    $stringable = new class {
        public function __toString(): string
        {
            return "stringable";
        }
    };
    $arguments = ["text", 7, 2.5, true, false, null, $stringable];
    $method = new \ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

    expect($method->invoke(Book::query(), $arguments))->toBe(implode("_", $arguments));
});
