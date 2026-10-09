<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithFakeRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CountingBook;

// Recording the tables a compiled-away subquery reads means resolving the
// relation. Resolving it a second time, alongside Laravel's own resolution,
// runs any side effect in the relation method twice — so the recording is done
// from getRelationWithoutConstraints(), the one point Laravel turns a relation
// name into a Relation. These pin that: one name, one call.
//
// The counts are asserted exactly. "At least one" would pass on the two-call
// version these tests exist to rule out.

test('has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::has("author")->get(),
    ))->toEqual(1);
});

// A count constraint takes addWhereCountQuery() rather than
// addWhereExistsQuery(), which is the branch the recording exists for.
test('count constrained has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::has("author", ">", 0)->get(),
    ))->toEqual(1);
});

test('doesnt have resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::doesntHave("author")->get(),
    ))->toEqual(1);
});

test('where has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::whereHas("author", fn ($query) => $query->where("id", ">", 0))->get(),
    ))->toEqual(1);
});

test('with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount("author")->get(),
    ))->toEqual(1);
});

test('with exists resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withExists("author")->get(),
    ))->toEqual(1);
});

// An aliased aggregate names the relation as "author as writer_count".
// Laravel splits the alias off before resolving; nothing here may resolve
// the unsplit string itself.
test('aliased with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount("author as writer_count")->get(),
    ))->toEqual(1);
});

// A constrained aggregate arrives as ["author" => Closure], where the
// relation name is the array key rather than the value.
test('constrained with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount([
            "author" => fn ($query) => $query->where("id", ">", 0),
        ])->get(),
    ))->toEqual(1);
});

// A method that is not a relation still gets called by Laravel, so whatever
// it returns arrives at the recording. Anything other than a Relation is
// handed straight back, and Laravel raises the error — the throw site is
// the assertion, because recording a value it cannot use moves the failure
// into this package and reports it against the wrong file.
test('a non relation return value fails in laravel rather than here', function () {
    $exception = null;

    try {
        (new BookWithFakeRelation)->withCount("notReallyARelation")->get();
    } catch (Throwable $thrown) {
        $exception = $thrown;
    }

    expect($exception)->toBeInstanceOf(Throwable::class);
    expect($exception->getFile())->not->toContain(
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "src",
    );
});

// A segment naming no relation ends the walk instead of calling it, so the
// failure is raised by Laravel's own resolution rather than by the walk.
// The tally is what pins that: the walk resolved the first segment and then
// stopped, and Laravel resolved it a second time on its way to the error.
// Calling the unknown segment from the walk would raise the same exception
// one resolution earlier, and the count is the only way to tell them apart.
test('unknown segment ends the walk and leaves the error to laravel', function () {
    try {
        tally(fn () => CountingBook::has("author.notARelation")->get());

        $this->fail("Expected a BadMethodCallException.");
    } catch (BadMethodCallException $exception) {
        expect(CountingBook::$authorCalls)->toEqual(2);
    }
});

// The documented exception. has() sends a dotted chain to hasNested(),
// which resolves each segment against a builder that gets compiled away,
// so the first segment is walked explicitly as well as resolved by
// Laravel. Two calls is the accepted cost of tagging the whole chain.
test('dotted chain resolves the first segment twice', function () {
    expect(tally(
        fn () => CountingBook::has("author.books")->get(),
    ))->toEqual(2);
});

function tally(callable $query): int
{
    CountingBook::$authorCalls = 0;
    $query();

    return CountingBook::$authorCalls;
}
