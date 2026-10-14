<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CountingBook;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function makeCompositeOfManyRelation(Author $author): HasOne
{
    $relation = Relation::noConstraints(
        fn () => (new Author)->latestBookByPriceThenDate(),
    );
    $relation->addEagerConstraints([$author]);

    return $relation;
}

function printersHavingIdAbove(Author $author, array $value)
{
    return $author->printers()
        ->groupBy("printers.id")
        ->having("printers.id", ">", $value);
}

function throughReadArguments(Author $author, array $arguments): array
{
    return collect($arguments)
        ->map(fn ($argument) => $argument === 'first printer'
            ? DB::table('printers')
                ->join('books', 'books.id', '=', 'printers.book_id')
                ->where('books.author_id', $author->id)
                ->min('printers.id')
            : $argument)
        ->all();
}

function throughReadSummary(mixed $result): mixed
{
    if ($result instanceof Paginator) {
        return $result->getCollection()->pluck('id')->all() ?: null;
    }

    if ($result instanceof Collection) {
        return $result->all() === []
            ? null
            : $result->map(fn ($item) => $item instanceof Model ? $item->getKey() : $item)->all();
    }

    if ($result instanceof Model) {
        return $result->getKey();
    }

    return $result;
}

function hasManyThroughReads(): array
{
    return [
        'get' => ['get', []],
        'first' => ['first', []],
        'find' => ['find', ['first printer']],
        'count' => ['count', []],
        'paginate' => ['paginate', [5]],
        'pluck' => ['pluck', ['printers.name']],
        'avg' => ['avg', ['printers.id']],
        'sum' => ['sum', ['printers.id']],
        'min' => ['min', ['printers.id']],
        'max' => ['max', ['printers.id']],
        'exists' => ['exists', []],
        'value' => ['value', ['printers.name']],
    ];
}

function deletePrintersBehindTheCache(Author $author): void
{
    DB::table('printers')
        ->whereIn('book_id', DB::table('books')->where('author_id', $author->id)->select('id'))
        ->delete();
}

function tally(callable $query): int
{
    CountingBook::$authorCalls = 0;
    $query();

    return CountingBook::$authorCalls;
}
