<?php

use GeneaLabs\LaravelModelCaching\RelationResolver;

function rememberedRelationPaths(): array
{
    return (new ReflectionProperty(RelationResolver::class, "classRelationPaths"))->getValue();
}

function rememberedRelationPathCount(): int
{
    return collect(rememberedRelationPaths())
        ->sum(fn (array $paths): int => count($paths));
}

function keyTagsAndTtlFromEmptyMemory(callable $query): array
{
    RelationResolver::forgetClassRelationPaths();
    $result = cacheKeyTagsAndTtl($query());
    RelationResolver::forgetClassRelationPaths();

    return $result;
}
