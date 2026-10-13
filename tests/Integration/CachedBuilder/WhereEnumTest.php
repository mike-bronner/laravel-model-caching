<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOrder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\IntegerStatus;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StringStatus;

test('where with integer backed enum', function () {
    $authors = (new Author)
        ->where('id', IntegerStatus::Active)
        ->get();

    expect($authors)->not->toBeNull();
    expect($authors)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('where with string backed enum', function () {
    $authors = (new Author)
        ->where('name', StringStatus::Active)
        ->get();

    expect($authors)->not->toBeNull();
    expect($authors)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('where in with integer backed enums', function () {
    $authors = (new Author)
        ->whereIn('id', [IntegerStatus::Active, IntegerStatus::Inactive])
        ->get();

    expect($authors)->not->toBeNull();
    expect($authors)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('where in with string backed enums', function () {
    $authors = (new Author)
        ->whereIn('name', [StringStatus::Active, StringStatus::Inactive])
        ->get();

    expect($authors)->not->toBeNull();
    expect($authors)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('where with a pure enum keys and reads by case name', function () {
    Author::factory()->create(['name' => 'Newest']);
    Author::factory()->create(['name' => 'Oldest']);

    $newest = (new Author)->where('name', AuthorOrder::Newest)->get();
    $oldest = (new Author)->where('name', AuthorOrder::Oldest)->get();

    expect(cacheKey((new Author)->where('name', AuthorOrder::Newest)))->toContain('-name_=_Newest')
        ->and($newest->pluck('name')->all())->toBe(['Newest'])
        ->and($oldest->pluck('name')->all())->toBe(['Oldest']);
});
