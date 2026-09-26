---
paths:
  - 'src/**'
---

# Src

## Resolve services through the container
Read config, cache and db through `Container::getInstance()->make("config"|"cache"|"db")`, not `config()`, `app()`, `resolve()` or facades. Do not constructor-inject services; build collaborators from the container inside the class. Build `CacheKey` and `CacheTags` inline per call with `(new CacheKey(...))->make()`, since they are throwaway builders, not services.

## Traits carry behaviour, Cached* classes compose them
Put new caching behaviour in a trait under `src/Traits`, mixed into a `Cached<LaravelClass>` subclass of the Eloquent class it replaces. Resolve trait method collisions with `insteadof`/`as` rather than duplicating code. There is no Actions, Services or Contracts layer.

## Builder writes flush through flushCacheAfterBuilderWrite
Every overridden builder write calls `flushCacheAfterBuilderWrite('<operation>')` and executes through `executeOnInnerOrParent()`, so a composed custom builder is honoured.

## Wrap cache I/O in withCacheFallback
Run every cache read, write or flush inside `$this->withCacheFallback(fn, '<short context>', $dbFallback)` instead of its own try/catch. Connection failures then fall back to the database when configured, and every other error still propagates.

## Get the current time with (new Carbon)->now()
Use `(new Carbon)->now()` with `Illuminate\Support\Carbon` for timestamps, not the `now()` helper or `Carbon::now()`.

## Detect capabilities by duck typing
Shared traits run on models, builders and relations alike, so probe the host with `method_exists()`/`property_exists()` (for example `method_exists($model, 'flushCache')`). Do not add interfaces or `instanceof` checks against new contracts.

## Docblocks explain the reason, not the signature
Add a docblock only where behaviour is not obvious, written as prose on why the code works this way and what breaks otherwise. Do not add `@param`/`@return` boilerplate for self-evident signatures.

## Pass small results as arrays, not DTOs
Return small multi-value results as arrays or positional tuples destructured with `[...] =`. Do not introduce DTO or readonly value classes.
