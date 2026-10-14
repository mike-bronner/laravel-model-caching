<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function eagerLoadedMembership(Model $model, string $relation): array
{
    return $model->newQuery()
        ->with($relation)
        ->get()
        ->mapWithKeys(fn (Model $parent): array => [$parent->getKey() => $parent->{$relation}])
        ->map(fn (EloquentCollection $related): array => sortedPivotKeys($related->modelKeys()))
        ->filter()
        ->sortKeys()
        ->all();
}

function pivotMembership(
    string $table,
    string $parentKey,
    string $relatedKey,
    array $where = [],
): array {
    return DB::table($table)
        ->where($where)
        ->get()
        ->groupBy($parentKey)
        ->map(fn ($rows): array => sortedPivotKeys($rows->pluck($relatedKey)))
        ->sortKeys()
        ->all();
}

function sortedPivotKeys(array|Collection $keys): array
{
    return collect($keys)
        ->map(fn ($key): int => (int) $key)
        ->sort()
        ->values()
        ->all();
}

function bookStoreMembership(): array
{
    return pivotMembership("book_store", "book_id", "store_id");
}

function storeBookMembership(): array
{
    return pivotMembership("book_store", "store_id", "book_id");
}

function postTagMembership(): array
{
    return pivotMembership("taggables", "taggable_id", "tag_id", ["taggable_type" => Post::class]);
}

function bookWithStores(): int
{
    return (int) DB::table("book_store")
        ->value("book_id");
}

function newBookStoreRow(): array
{
    $bookId = bookWithStores();
    $attachedStoreIds = DB::table("book_store")
        ->where("book_id", $bookId)
        ->select("store_id");
    $storeId = DB::table("stores")
        ->whereNotIn("id", $attachedStoreIds)
        ->value("id");

    return ["book_id" => $bookId, "store_id" => (int) $storeId];
}

function newPostTagRow(): array
{
    $postId = (int) DB::table("posts")
        ->value("id");
    $attachedTagIds = DB::table("taggables")
        ->where("taggable_id", $postId)
        ->where("taggable_type", Post::class)
        ->select("tag_id");
    $tagId = DB::table("tags")
        ->whereNotIn("id", $attachedTagIds)
        ->value("id");

    return ["tag_id" => (int) $tagId, "taggable_id" => $postId, "taggable_type" => Post::class];
}

function useTenantConnection(): void
{
    config(["database.connections.tenant" => config("database.connections.testing")]);
}

function userRoleMembership(): array
{
    return pivotMembership("role_user", "user_id", "role_id");
}

function newRoleUserRow(): array
{
    $row = DB::table("users")
        ->crossJoin("roles")
        ->select("users.id as userId", "roles.id as roleId")
        ->whereNotExists(fn ($query) => $query->from("role_user")
            ->whereColumn("role_user.user_id", "users.id")
            ->whereColumn("role_user.role_id", "roles.id"))
        ->first();

    return ["role_id" => (int) $row->roleId, "user_id" => (int) $row->userId];
}
