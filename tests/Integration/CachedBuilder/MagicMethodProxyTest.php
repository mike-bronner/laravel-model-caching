<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Builder;

afterEach(function () {
    $reflection = new \ReflectionClass(Builder::class);
    $macros = $reflection->getStaticPropertyValue('macros');
    unset($macros['type'], $macros['ofType'], $macros['customFilter']);
    $reflection->setStaticPropertyValue('macros', $macros);
});

test('global macro proxy does not throw bad method call exception', function () {
    Builder::macro('type', function (string $type) {
        /** @var Builder $this */
        return $this->where('name', 'like', "%{$type}%");
    });

    Author::factory()->count(3)->create(['name' => 'ZZZFICTION-UNIQUE-TEST Author']);
    Author::factory()->count(2)->create(['name' => 'ZZZOTHER-UNIQUE-TEST Author']);

    $fictionAuthors = Author::type('ZZZFICTION-UNIQUE-TEST')->get();

    expect($fictionAuthors)->not->toBeEmpty();
    expect($fictionAuthors)->toHaveCount(3);
});

test('global macro produces distinct cache keys', function () {
    Builder::macro('type', function (string $type) {
        /** @var Builder $this */
        return $this->where('name', 'like', "%{$type}%");
    });

    Author::factory()->create(['name' => 'ZZZFICTION-UNIQUE Author']);
    Author::factory()->create(['name' => 'ZZZNONFICTION-UNIQUE Author']);

    $fiction = Author::type('ZZZFICTION-UNIQUE')->get();
    $nonFiction = Author::type('ZZZNONFICTION-UNIQUE')->get();

    expect($fiction)->toHaveCount(1);
    expect($nonFiction)->toHaveCount(1);
    expect($nonFiction->first()->id)->not->toEqual($fiction->first()->id);
});

test('two different global macros produce distinct cache keys', function () {
    Builder::macro('ofType', function (string $type) {
        /** @var Builder $this */
        return $this->where('name', 'like', "%{$type}%");
    });

    Builder::macro('customFilter', function (string $value) {
        /** @var Builder $this */
        return $this->where('email', 'like', "%{$value}%");
    });

    Author::factory()->create(['name' => 'ZZZSCIENCE-UNIQUE Author', 'email' => 'zzzsci-unique@example.com']);
    Author::factory()->create(['name' => 'ZZZHISTORY-UNIQUE Author', 'email' => 'zzzhistory-unique@example.com']);

    $byName = Author::ofType('ZZZSCIENCE-UNIQUE')->get();
    $byEmail = Author::customFilter('zzzhistory-unique')->get();

    expect($byName)->toHaveCount(1);
    expect($byEmail)->toHaveCount(1);
    expect($byEmail->first()->id)->not->toEqual($byName->first()->id);
});

test('global macro results are cached', function () {
    Builder::macro('type', function (string $type) {
        /** @var Builder $this */
        return $this->where('name', 'like', "%{$type}%");
    });

    Author::factory()->create(['name' => 'ZZZCACHED-UNIQUE Author']);
    Author::factory()->create(['name' => 'ZZZCACHED-UNIQUE Author 2']);

    $first = Author::type('ZZZCACHED-UNIQUE')->get();
    $second = Author::type('ZZZCACHED-UNIQUE')->get();

    expect($first)->toHaveCount(2);
    expect($second)->toHaveCount(
        2,
        'Repeated call with same args should return identical cached results.',
    );
    expect($second->pluck('id')->sort()->values()->toArray())->toEqual(
        $first->pluck('id')->sort()->values()->toArray(),
        'Both calls should return the same records (from cache).',
    );
});

test('existing builder methods still work with cached builder', function () {
    Author::factory()->create(['name' => 'Alice']);
    Author::factory()->create(['name' => 'Bob']);

    $result = Author::where('name', 'Alice')->get();

    expect($result)->toHaveCount(1);
    expect($result->first()->name)->toEqual('Alice');
});

test('local scope still works through cached builder', function () {
    Author::factory()->create(['name' => 'Alpha Author']);
    Author::factory()->create(['name' => 'Beta Author']);

    $alphas = Author::startsWithA()->get();
    $uncachedAlphas = (new UncachedAuthor)->startsWithA()->get();

    expect($alphas->count())->toEqual($uncachedAlphas->count());
    expect($alphas->every(fn ($a) => str_starts_with($a->name, 'A')))->toBeTrue();
});
