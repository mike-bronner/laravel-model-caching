<?php

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function cacheRelationRead(Model $parent, string $relation): string
{
    $key = sha1(cacheKey($parent->{$relation}()));
    $parent->{$relation};

    return $key;
}

function cachedRelationValue(string $key, array $tags): mixed
{
    $cached = test()->cache()
        ->tags($tags)
        ->get($key);

    return data_get($cached, "value");
}

function plantTaggedSentinels(array $tags): array
{
    return collect($tags)
        ->mapWithKeys(function (string $tag): array {
            $key = "pivot-flush-sentinel-" . Str::random();
            test()->cache()
                ->tags([$tag])
                ->forever($key, true);

            return [$tag => $key];
        })
        ->all();
}

function survivingSentinels(array $sentinels): array
{
    return collect($sentinels)
        ->filter(fn (string $key, string $tag): bool => test()->cache()
            ->tags([$tag])
            ->get($key) === true)
        ->keys()
        ->all();
}

function writeWithoutModelEvents(callable $write): void
{
    Model::withoutEvents($write);
}

function expectPivotWriteToFlushRead(Closure $write, array $case): void
{
    $parent = data_get($case, "parent");
    $relation = data_get($case, "relation");
    $readTags = data_get($case, "readTags");
    $key = cacheRelationRead($parent, $relation);
    $flushed = plantTaggedSentinels(data_get($case, "pivotTags"));
    $kept = plantTaggedSentinels(data_get($case, "keptTags"));
    $pivotRelation = $parent->{$relation}();

    expect(cachedRelationValue($key, $readTags))->not
        ->toBeNull();

    writeWithoutModelEvents(fn () => $write($pivotRelation, $case));

    expect(cachedRelationValue($key, $readTags))->toBeNull();
    expect(survivingSentinels($flushed))->toBe([]);
    expect(survivingSentinels($kept))->toBe(array_keys($kept));
}

function bookStoreRowIds(): array
{
    return pivotRowIds("book_store", "book_id", "store_id", "stores");
}

function storeBookRowIds(): array
{
    return pivotRowIds("book_store", "store_id", "book_id", "books");
}

function postTagRowIds(): array
{
    $postsOnly = ["taggable_type" => Post::class];

    return pivotRowIds("taggables", "taggable_id", "tag_id", "tags", $postsOnly);
}

function pivotRowIds(
    string $table,
    string $parentColumn,
    string $relatedColumn,
    string $relatedTable,
    array $where = [],
): array {
    $row = DB::table($table)
        ->where($where)
        ->first();
    $parentId = (int) data_get($row, $parentColumn);
    $attachedIds = DB::table($table)
        ->where($where)
        ->where($parentColumn, $parentId)
        ->select($relatedColumn);
    $unattachedId = DB::table($relatedTable)
        ->whereNotIn("id", $attachedIds)
        ->value("id");

    return [$parentId, (int) data_get($row, $relatedColumn), (int) $unattachedId];
}

function spyOnRelationInvalidations(Relation $relation): ArrayObject
{
    $calls = new ArrayObject;
    $store = app("cache")
        ->store();
    $spy = new class ($store, $calls) extends ModelCacheRepository {
        public function __construct(Repository $repository, protected ArrayObject $calls)
        {
            parent::__construct($repository);
        }

        public function invalidateTags(array $tags): void
        {
            $this->calls
                ->append($tags);
            parent::invalidateTags($tags);
        }
    };
    $repositories = new ReflectionProperty($relation, "modelCacheRepositories");
    $map = $repositories->getValue() ?? new WeakMap;
    $map[$relation] = $spy;
    $repositories->setValue(null, $map);

    return $calls;
}
