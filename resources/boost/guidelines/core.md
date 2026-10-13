# Model Caching for Laravel

This application installs `mike-bronner/laravel-model-caching`. The package
caches Eloquent query results and invalidates them from Eloquent events.
Everything below is behaviour of the installed package. None of it is generic
cache advice, and none of it applies to models that have not opted in.

## Turning it on

A model is cached when it uses the trait, and not otherwise.

```php
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Cachable;
}
```

`GeneaLabs\LaravelModelCaching\CachedModel` is an abstract model that already
uses the trait, so extending it has the same effect. There is nothing to
register. The service provider is auto-discovered.

## What the trait changes

The trait replaces the model's Eloquent builder with `CachedBuilder`, which
serves these from the cache: `all`, `get`, `first`, `find`, `paginate`,
`pluck`, `value`, `exists`, `count`, `sum`, `avg`, `min` and `max`.

Write queries exactly as you would without the package. Do not wrap them in
`Cache::remember()`, do not invent cache keys, and do not add
`Cache::forget()` calls. Doing any of that caches the same rows twice under
keys the package cannot invalidate.

Entries are written with `rememberForever`. There is no time to live and no
per-query expiry option, so freshness comes from invalidation alone.

## What invalidates an entry

Invalidation is driven by writes that pass through the model. The trait
listens for `created`, `saved` and `deleted` on the model, and for the pivot
events `pivotAttached`, `pivotDetached`, `pivotSynced` and `pivotUpdated`.
`Post::destroy()` flushes too.

The model's query builder flushes on its own writes as well, with or without
model events: `insert`, `insertGetId`, `insertOrIgnore`,
`insertOrIgnoreReturning`, `insertUsing`, `insertOrIgnoreUsing`, `upsert`,
`update`, `updateOrInsert`, `updateFrom`, `increment`, `decrement`,
`incrementEach`, `decrementEach`, `touch`, `delete`, `forceDelete` and
`truncate`. That covers `Post::upsert(...)` and `Post::where(...)->update(...)`,
and it covers `saveQuietly()`, `deleteQuietly()` and `withoutEvents()`, because
the insert, update or delete underneath each of them is one of those writes.

A write that does not go through the model's builder leaves the cache stale.
`DB::table('posts')->update(...)` is the common case. A base query builder taken
from the model with `toBase()` or `getQuery()` is another. Write through the
model, or call `ModelCache::invalidate(Post::class)` afterwards.

A flush happens when the write runs, not when a surrounding database
transaction commits. `insert`, `update`, `increment`, `decrement` and
`truncate` flush just before their statement runs, and the other writes flush
just after it. In both cases a concurrent read can refill the entry from data
the transaction has not committed yet. Invalidate again after the commit where
that matters.

Tags are built for the model itself, each eager-loaded relationship, each
joined table and each morph-to target, so a write invalidates the queries that
read the written table rather than the whole cache.

## When a query is not served from cache

- Caching is off globally, through the `enabled` config value.
- The chain called `disableCache()`. That affects only that chain.
- The code runs inside `ModelCache::runDisabled(...)`, which turns the config
  value off for the duration of the closure and restores it afterwards, also
  when the closure throws.
- The query calls `inRandomOrder()`, whose results are meant to differ.
- The query holds a lock from `lockForUpdate()` or `sharedLock()`. A locked
  read has to reach the database, so nothing is read from or written to cache.
- The query eager-loads a relationship whose related model does not use
  `Cachable`.

Only the `enabled` value stops a write from invalidating, and `runDisabled()`
works by switching it off. A write through a chain that called
`disableCache()` or `inRandomOrder()`, took a lock or eager-loads an uncached
model still flushes, because it still makes other cached reads stale.

Writes inside `runDisabled()` invalidate nothing, on purpose. Calling
`flushCache()` inside the closure does nothing either, because caching is off
there. The entries cached before the closure keep being served after it, so
invalidate once the closure has returned:

```php
ModelCache::runDisabled(fn () => Post::whereKey($id)->update($changes));
ModelCache::invalidate(Post::class);
```

The eager-load case is the trap worth remembering. It disables caching for the
**whole query**, not just for the relationship. Adding `with('author')` to a
cached `Post` silently turns `Post` caching off when `Author` is a plain
model. If caching appears to have stopped working on a model, check the
`Cachable` status of everything it eager-loads first.

## The cache store decides how invalidation behaves

Tag-based invalidation needs a cache store whose Laravel store class extends
`Illuminate\Cache\TaggableStore`. Redis is the store this package is developed
and tested against.

DynamoDB is handled differently. Invalidation rotates a namespace version key
rather than using tags, which makes old rows unreachable at once and leaves
their physical removal to DynamoDB TTL. Enable TTL on the table's expiration
attribute, and expect dead rows to linger on high-churn models.

On any other store the package cannot scope an invalidation, so it flushes the
whole repository instead. One model save then clears everything the
application keeps in that store. When the default store is not taggable, give
the model cache a store of its own:

```
MODEL_CACHE_STORE=model-cache
```

`php artisan modelCache:clear` with no `--model` follows the same split. On
Redis it deletes this package's entries and cool-down keys and nothing else,
whatever the store prefix is, so the application's own entries in the same
store survive. It recognises this package's keys by the
`genealabs:laravel-model-caching:` prefix that every one of its tags and
cool-down keys carries. On DynamoDB it rotates the namespace. On anything else it
flushes the entire store.

## Configuration

The config file is `config/laravel-model-caching.php`, published with
`php artisan modelCache:publish --config`.

| Key | Environment variable | Effect |
|-----|----------------------|--------|
| `enabled` | `MODEL_CACHE_ENABLED` | Turns all caching off when false. Default true. |
| `store` | `MODEL_CACHE_STORE` | Names the cache store to use. The default store is used when unset. |
| `use-database-keying` | `MODEL_CACHE_USE_DATABASE_KEYING` | Puts the connection name and database name into the cache prefix. Default true, which keeps entries separate across connections. |
| `fallback-to-database` | `MODEL_CACHE_FALLBACK_TO_DB` | Logs a warning and queries the database when the cache backend cannot be reached, instead of throwing. Default false. |
| `cache-prefix` | none | Prefixes entries. Set in the config file only. |

`fallback-to-database` covers connection failures from Redis, Predis and
DynamoDB. Any other exception still propagates, so it is not a general
swallow-everything switch.

A per-model prefix is a property on the model:

```php
protected $cachePrefix = 'tenant-123';
```

Changing `cache-prefix`, a model's `$cachePrefix`, the connection name or the
database name changes the keys the package reads. Existing entries are not
deleted by the change, they simply stop being found. The affected queries read
cold once and the orphaned entries stay until something else removes them.
Plan tenant prefix changes with that in mind.

## Cache cool-down

Cool-down keeps a burst of writes from flushing the cache on every one of
them. It takes two steps, and the first alone does nothing.

Declare the default duration on the model:

```php
protected $cacheCooldownSeconds = 300;
```

Then activate it from a query, which is what writes the cool-down window into
the cache store:

```php
Comment::withCacheCooldownSeconds()->get();
Comment::withCacheCooldownSeconds(30)->get();
```

During an active window, writes to the model record themselves and do not
flush. That holds for model events, `Post::destroy()`, pivot `attach`,
`detach`, `sync` and `updateExistingPivot`, and every builder write listed
under "What invalidates an entry", including `increment()`. Two things still
flush inside a window: an explicit `flushCache()` or
`ModelCache::invalidate()`, and a write through a different model class that
shares the table, because the window belongs to one model class.

Cool-down does not hold for a model that declares `$cachePrefix`, and on pivot
writes it is not guaranteed when the two models declare different
`$cachePrefix` values. Writes to such models flush as if no window were
active. Issue #643 tracks this.

Once the window has run out, the next cached read of the model flushes its
cache and ends the window, so what the window held back is dropped even when no
further write comes. A write after the window has run out also flushes.

A `withCacheCooldownSeconds()` query starts a window only when none is
recorded. One made during a window does not extend it, and one made after the
window has run out is the read that ends it. The next one after that starts a
new window.

Do not put a cool-down on a model whose reads must be current, such as the
authenticated user.

## Invalidating by hand

```php
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;

ModelCache::invalidate(App\Models\Post::class);
ModelCache::invalidate([App\Models\Post::class, App\Models\Comment::class]);
```

`invalidate()` throws `InvalidArgumentException` for a class that does not use
the `Cachable` trait, so it will not silently do nothing. The Artisan
equivalents are `php artisan modelCache:clear --model='App\Models\Post'` for
one model and `php artisan modelCache:clear` for the whole model cache.

## Custom Eloquent builders

A model's own `newEloquentBuilder()` keeps working. A custom builder that
extends `CachedBuilder` is used directly. One that does not is wrapped inside a
`CachedBuilder`, which proxies unknown method calls through to it, so custom
query methods stay callable, whatever arguments they take. Each call is
recorded in the cache key with its arguments, so two calls with different
arguments are cached apart. Closures are the exception: every closure is
recorded the same way, so two calls are told apart only when their closures
build different SQL. A custom method that uses a closure some other way, such
as to filter results after the query, can be served another call's cached
rows. Issue #644 tracks this. A custom method that writes, such as its own
`updateOrInsert()`, invalidates like the builder write it is named after.

`ModelCaching::useEloquentBuilder()` is deprecated and raises a deprecation
notice. Do not call it. Return your builder from `newEloquentBuilder()`
instead.

## Static analysis

Larastan resolves most of the package with no annotation. The scopes
`disableCache()` and `withCacheCooldownSeconds()` resolve anywhere in a query
chain, and `flushCache()` resolves on a model instance.

A query chain that ends in a `CachedBuilder` method does not. Larastan types
`Post::where(...)` as Eloquent's own builder, so `->flushCache()` and
`->cache()` on it are reported as undefined methods. `CachedBuilder` is not
generic, so naming it as the model's builder type trades these errors for
others: Larastan then no longer finds the scopes above, and query results lose
their model type. A `@mixin CachedBuilder` on the model does not reach a chain
either. Do not add a `newEloquentBuilder()` override for this. Use the calls
that resolve instead:

```php
use App\Models\Post;
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;

(new Post)->flushCache();
ModelCache::invalidate(Post::class);
```

Both do nothing while caching is disabled, through the `enabled` config value
or inside `runDisabled()`. A chained `flushCache()` or `cache()` throws
`BadMethodCallException` there instead, because the model then returns
Eloquent's own builder. Suppressing the PHPStan error on a chain is the
consuming project's own choice, and that runtime case still applies to it.

## Testing

Either turn caching off for the suite:

```php
config(['laravel-model-caching.enabled' => false]);
```

Or point it at a store of its own, which is the option to take when the
behaviour under test is the caching itself:

```php
config(['cache.stores.model-test' => ['driver' => 'array']]);
config(['laravel-model-caching.store' => 'model-test']);
```

## Requirements

PHP 8.3 or later, and Laravel 12 or 13.
