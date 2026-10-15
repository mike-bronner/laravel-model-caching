# 🚀 Model Caching for Laravel

[![Latest Stable Version](https://img.shields.io/packagist/v/mikebronner/laravel-model-caching)](https://packagist.org/packages/mikebronner/laravel-model-caching)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20)](https://laravel.com)
[![Laravel Package](https://github.com/mikebronner/laravel-model-caching/workflows/Laravel%20Package/badge.svg?branch=master)](https://github.com/mikebronner/laravel-model-caching/actions?query=workflow%3A%22Laravel+Package%22)
[![PHP Version](https://img.shields.io/packagist/php-v/mikebronner/laravel-model-caching)](https://packagist.org/packages/mikebronner/laravel-model-caching)
[![Packagist](https://img.shields.io/packagist/dt/GeneaLabs/laravel-model-caching.svg)](https://packagist.org/packages/genealabs/laravel-model-caching)
[![codecov](https://codecov.io/gh/mike-bronner/laravel-model-caching/graph/badge.svg?token=ACk1Kk4OLO)](https://codecov.io/gh/mike-bronner/laravel-model-caching)
[![Tests](https://img.shields.io/badge/tests-335%2B-brightgreen)](https://github.com/mikebronner/laravel-model-caching/tree/master/tests)
[![GitHub Stars](https://img.shields.io/github/stars/mikebronner/laravel-model-caching)](https://github.com/mikebronner/laravel-model-caching/stargazers)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/mikebronner/laravel-model-caching/master/LICENSE)

![Model Caching for Laravel masthead image](https://repository-images.githubusercontent.com/103836049/b0d89480-f1b1-11e9-8e13-a7055f008fe6)

## 🗂️ Table of Contents
- [📖 Summary](#-summary)
- [📦 Installation](#-installation)
- [🚀 Getting Started](#-getting-started)
- [⚙️ Configuration](#️-configuration)
- [🤝 Contributing](#-contributing)
- [⬆️ Upgrading](#️-upgrading)
- [🔐 Security](#-security)
- [📚 Further Reading](#-further-reading)

## 📖 Summary
Automatic, self-invalidating Eloquent model and relationship caching. Add a
trait to your models and all query results are cached automatically — no manual
cache keys, no forgetting to invalidate. When a model is created, updated, or
deleted the relevant cache entries are flushed for you.

⚡ Typical performance improvements range from 100–900% reduction in database
queries on read-heavy pages. 🧪 Backed by 335+ integration tests across PHP
8.3–8.5 and Laravel 12–13.

**Use this package when** your application makes many repeated Eloquent queries
and you want a drop-in caching layer that stays in sync with your data without
any manual bookkeeping.

### 🔄 Before & After

❌ **Without this package** — manual cache keys, manual invalidation:
```php
$posts = Cache::remember('posts:active:page:1', 3600, function () {
    return Post::where('active', true)->with('comments')->paginate();
});

// And in every observer or event listener…
Cache::forget('posts:active:page:1');
// Hope you remembered every key variant! 😅
```

✅ **With this package** — add the trait, query normally:
```php
// Just query. Caching and invalidation happen automatically. ✨
$posts = Post::where('active', true)->with('comments')->paginate();
```

### ✅ What Gets Cached
- Model queries (`get`, `first`, `find`, `all`, `paginate`, `pluck`, `value`, `exists`)
- Aggregations (`count`, `sum`, `avg`, `min`, `max`)
- Eager-loaded relationships (via `with()`)
- Lazy-loaded relationships, such as `$post->comments`, are cached the same
  way as eager-loaded ones when the related model uses `Cachable`. The related
  model's query also runs through `CachedBuilder`, so you do not need `with()`
  to get caching.
- Custom column selections: `select()`, `addSelect()` and raw expressions are
  cached. The selected columns form part of the cache key.

### 🚫 What Does Not Get Cached
- `inRandomOrder()` queries — caching is automatically disabled since results should differ each time.
- `lockForUpdate()` and `sharedLock()` queries. They read from the database
  directly, and their results are never written to the cache.
- A query that eager-loads a relation whose related model does not use
  `Cachable`. That one relation stops the whole query from being cached: the
  base model's query is not cached, whatever other relations the same `with()`
  call lists. For example, `Post::with('author')` is not cached when `Author`
  is a plain model. Check this first when caching seems to stop working. Each
  eager-loaded relation still runs a query of its own, and that query is cached
  or not by its own rules. The check covers first-level relations only: in
  `with('comments.replies')` it checks `Comment`, but not `Reply`.
- A macro or custom Eloquent builder method called with a closure, or with an
  object that has no `__toString()`, also inside an array argument. The key
  cannot tell two such arguments apart, so the query runs live. Two kinds of
  object are keyed and cached: an enum passed directly, by its value or name,
  and a query builder or relation, by its own query. A value JSON cannot
  encode, such as `NAN` or a string that is not valid UTF-8, also runs live.

### 💾 Cache Drivers

| Driver | Supported |
|--------|-----------|
| Redis | ✅ (recommended) |
| Memcached | ✅ |
| APC | ✅ |
| DynamoDB | ✅ |
| Array | ❌ |
| File | ❌ |
| Database | ❌ |

### 📋 Requirements
- PHP 8.3+
- Laravel 12 or 13
- PHP 8.6 needs Laravel 13. Laravel 12 does not support PHP 8.6, because its
  session handler fails there. Laravel 13 fixes it.

## 📦 Installation
```
composer require mike-bronner/laravel-model-caching
```

✨ The service provider is auto-discovered. No additional setup is required.

## 🚀 Getting Started
Add the `Cachable` trait to your models. The recommended approach is a base
model that all other models extend:

```php
<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use Cachable;
}
```

Alternatively, extend the included `CachedModel` directly:

```php
<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\CachedModel;

class Post extends CachedModel
{
    // ...
}
```

🎉 That's it — all Eloquent queries and eager-loaded relationships on these
models are now cached and automatically invalidated.

> **⚠️ Note:** You can cache the `User` model — the `Cachable` trait does not
> conflict with Laravel's authentication. Just avoid using cache cool-down
> periods on it, and ensure user updates always go through Eloquent (not raw
> `DB::table()` queries) so cache invalidation fires correctly.

### 🌍 Real-World Example
Consider a blog with posts, comments, and tags:

```php
class Post extends BaseModel
{
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}

// All cached automatically — the query, the eager loads, everything. 🪄
$posts = Post::with('comments', 'tags')
    ->where('published', true)
    ->latest()
    ->paginate(15);
```

When a new comment is created, the cache for `Post` and `Comment` queries is
automatically invalidated — no manual `Cache::forget()` calls needed. 🧹

## ⚙️ Configuration
Publish the config file:
```sh
php artisan modelCache:publish --config
```

This creates `config/laravel-model-caching.php`:

```php
return [
    'cache-prefix'         => '',
    'enabled'              => env('MODEL_CACHE_ENABLED', true),
    'use-database-keying'  => env('MODEL_CACHE_USE_DATABASE_KEYING', true),
    'store'                => env('MODEL_CACHE_STORE'),
    'fallback-to-database' => env('MODEL_CACHE_FALLBACK_TO_DB', false),
];
```

### 🔧 Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MODEL_CACHE_ENABLED` | `true` | ✅ Enable or disable caching globally. |
| `MODEL_CACHE_STORE` | `null` | 💾 Cache store name from `config/cache.php`. Uses the default store when not set. |
| `MODEL_CACHE_USE_DATABASE_KEYING` | `true` | 🔑 Include database connection and name in cache keys. Important for multi-tenant or multi-database apps. |
| `MODEL_CACHE_FALLBACK_TO_DB` | `false` | 🛡️ When `true`, falls back to direct database queries if the cache backend is unavailable (e.g. Redis is down) instead of throwing an exception. |
| `MODEL_CACHE_TTL` | `null` | ⏰ Global cache expiration in whole seconds. Unset, empty or `0` means cache forever. Any other value that is not a whole number of seconds throws an exception. See [Cache Expiration (TTL)](#-cache-expiration-ttl) — a per-model `$cacheTtlSeconds` overrides this. |

> **📝 Note:** The `cache-prefix` option is set directly in the config file (not via
> an environment variable). For dynamic prefixes (e.g. multi-tenant), use the
> per-model `$cachePrefix` property shown below.

### 💾 Custom Cache Store
To use a dedicated cache store for model caching, define one in
`config/cache.php` and reference it:
```
MODEL_CACHE_STORE=model-cache
```

### ☁️ DynamoDB Cache Store
DynamoDB is supported when your selected Laravel cache store uses the
`dynamodb` driver:
```env
MODEL_CACHE_STORE=dynamodb-model
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_DYNAMODB_CACHE_ENDPOINT=
AWS_DYNAMODB_CACHE_TABLE=cache
```

Define the store in `config/cache.php` using the same fields Laravel documents
for the DynamoDB cache driver:
```php
'stores' => [
    'dynamodb-model' => [
        'driver' => 'dynamodb',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'table' => env('AWS_DYNAMODB_CACHE_TABLE', 'cache'),
        'endpoint' => env('AWS_DYNAMODB_CACHE_ENDPOINT'),
        'attributes' => [
            'key' => 'key',
            'value' => 'value',
            'expiration' => 'expires_at',
        ],
    ],
],
```

If your application does not already require it, install the AWS SDK:
```sh
composer require aws/aws-sdk-php
```

Enable DynamoDB TTL on the table's `expires_at` attribute as described in the
Laravel cache docs.

#### How invalidation works on DynamoDB
Model invalidation on DynamoDB uses logical namespace versioning instead of
native cache tags:
- `modelCache:clear` rotates a package-wide namespace key.
- Model and relationship invalidation rotate per-tag namespace keys.
- Cached query rows become unreachable immediately after the namespace changes.
- Old query rows are not deleted eagerly; DynamoDB removes them later through TTL.

This package does **not** issue table scans or destructive flushes on DynamoDB.

#### TTL guidance
Laravel's DynamoDB cache store writes `forever()` entries with a long-lived
expiration instead of a truly unbounded item. In current Laravel releases that
window is several years, which means stale DynamoDB rows are bounded but can
linger for a long time after invalidation.

When a query resolves a TTL (see [Cache Expiration (TTL)](#-cache-expiration-ttl)),
its row gets that expiration instead. Laravel stops serving the row once the
expiration passes, even before DynamoDB's TTL process deletes it.

Practical guidance:
- Always enable TTL on `expires_at`. Without it, DynamoDB never deletes expired or invalidated rows.
- Treat DynamoDB cache invalidation as logical invalidation first and physical cleanup later.
- Expect dead query rows to accumulate temporarily on write-heavy or high-churn models.
- Set a model cache TTL if you want invalidated query rows to become eligible for deletion sooner than Laravel's long-lived forever expiration.
- If you need faster physical cleanup than DynamoDB's TTL process gives, Redis is usually a better fit.

#### Operational notes
- Namespace control keys do not grow without bound. The package stores one global namespace key plus one key per normalized tag hash.
- Multi-tag invalidation is not atomic. Tags are rotated one at a time, so a crash in the middle of an invalidation can leave a partial namespace rotation. A later invalidation will still converge the cache to the latest version.
- Tag control keys hash the raw tag string, so long or punctuation-heavy tags are supported. Namespace collisions are limited to theoretical SHA-1 collisions.
- Cache cool-down metadata intentionally bypasses namespace versioning and stays on the raw cache store.

#### Troubleshooting
- **`modelCache:clear` did not shrink the DynamoDB table:** expected. The command makes old rows unreachable; it does not physically delete every row.
- **Stale rows are still visible in DynamoDB:** expected until TTL removes them.
- **Frequent invalidations increase table size:** expected on high-churn models because stale rows remain until TTL cleanup.
- **Connection failures during reads:** enable `MODEL_CACHE_FALLBACK_TO_DB=true` if you want query paths to fall back to the database during cache outages.
- **Connection failures during `modelCache:clear`:** the command now returns a non-zero exit code and prints the cache error instead of silently succeeding.

#### When to use DynamoDB vs Redis
- Use **DynamoDB** when you are already operating in AWS-native or serverless environments, want a managed cache store without running Redis, or need a simple multi-AZ DynamoDB-backed cache layer.
- Use **Redis** when you need lower latency, higher write churn, native tag support, or faster physical cleanup of invalidated cache data.

### 🏷️ Cache Key Prefix
For multi-tenant applications you can isolate cache entries per tenant. Set the
prefix globally in config:
```php
'cache-prefix' => 'tenant-123',
```

Or per-model via a property:
```php
<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Cachable;

    protected $cachePrefix = 'tenant-123';
}
```

### 🔌 Multiple Database Connections
When `use-database-keying` is enabled (the default), cache keys automatically
include the database connection and name. This keeps cache entries separate
across connections without any extra configuration.

### 🚫 Disabling Cache
There are three ways to bypass caching:

**1. Per-query** (only affects this query chain, not subsequent queries):
```php
$results = MyModel::disableCache()->where('active', true)->get();
```

**2. Globally via environment:**
```
MODEL_CACHE_ENABLED=false
```

**3. For a block of code:**
```php
$result = app('model-cache')->runDisabled(function () {
    return MyModel::get();
});

// or via the Facade
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;

ModelCache::runDisabled(function () {
    return MyModel::get();
});
```

`runDisabled()` restores the setting when the closure returns or throws.
Writes inside the closure do not invalidate anything, on purpose, and
`flushCache()` does nothing there either. Invalidate after the closure
returns:

```php
ModelCache::runDisabled(fn () => MyModel::whereKey($id)->update($changes));
ModelCache::invalidate(MyModel::class);
```

> **💡 Tip:** Use option 1 in seeders to avoid pulling stale cached data during
> reseeds.

### ❄️ Cache Cool-Down Period
In high-traffic scenarios (e.g. frequent comment submissions) you may want to
prevent every write from immediately flushing the cache. Cool-down requires two
steps:

**Declare the default duration** on the model (this alone does nothing — it
just sets the value):

```php
<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use Cachable;

    protected $cacheCooldownSeconds = 300; // 5 minutes ⏱️
}
```

**Activate the cool-down** by calling `withCacheCooldownSeconds()` in your
query. This writes the cool-down window into the cache store:

```php
// Activate using the model's default (300 seconds)
Comment::withCacheCooldownSeconds()->get();

// Or override with a specific duration
Comment::withCacheCooldownSeconds(30)->get();
```

Once activated, writes during the cool-down window do not flush the cache.
That covers model events, `destroy()`, pivot operations and every builder
write in the *Cache Invalidation* table, `increment()` included. An explicit
`flushCache()` or `ModelCache::invalidate()`, and a write through a different
model class on the same table, still flush, because the window belongs to one
model class. A model that declares `$cachePrefix` keeps its window under that
prefix, and every write checks it there, pivot writes included, whatever prefix
the other model declares.

After the window expires, the next cached read of the model flushes its cache
and ends the window, even if no write comes first. A write after expiry
flushes as well. 🔄

### 🛡️ Graceful Fallback
When enabled, if the cache backend (e.g. Redis) is unavailable the package logs
a warning and falls back to querying the database directly — your application
continues to function without caching rather than throwing an exception.

```
MODEL_CACHE_FALLBACK_TO_DB=true
```

### 🧹 Cache Invalidation
Cache is automatically flushed when:

| Trigger | Behavior |
|---------|----------|
| Model created | Flush model cache |
| Model updated/saved | Flush model cache |
| Model deleted | Flush only if rows were actually deleted |
| Model force-deleted | Flush only if rows were actually deleted |
| Pivot `attach` / `detach` / `sync` / `updateExistingPivot` | Flush relationship cache |
| `increment` / `decrement` / `incrementEach` / `decrementEach` | Flush model cache |
| `insert` / `insertGetId` / `insertOrIgnore` / `insertOrIgnoreReturning` / `insertUsing` / `insertOrIgnoreUsing` (builder) | Flush model cache |
| `update` / `upsert` / `updateOrInsert` / `updateFrom` / `touch` (builder) | Flush model cache |
| `truncate` | Flush model cache |

Builder writes flush with or without model events, so `saveQuietly()`,
`deleteQuietly()` and `withoutEvents()` still invalidate. A write that skips the
model's builder does not: `DB::table(...)`, or a base query builder taken with
`toBase()` or `getQuery()`. Call `ModelCache::invalidate(Post::class)` after
one of those.

A flush does not wait for a database transaction to commit. A model write
flushes on its Eloquent model event, which fires during the transaction, not
on commit. Builder writes flush when their statement runs. A rollback does not
undo a flush that already happened. Before the transaction ends, a read can
cache rows that the commit or rollback then changes. Call
`ModelCache::invalidate(Post::class)` after the transaction ends where that
matters.

Cache tags are generated for the primary model, each eager-loaded relationship,
joined tables, and morph-to target types, so only the relevant entries are
invalidated. 🎯

A subquery passed to `joinSub()`, `leftJoinSub()`, `rightJoinSub()`,
`crossJoinSub()`, `straightJoinSub()`, `joinLateral()`, `leftJoinLateral()`,
`fromSub()`, `whereIn()`, `orWhereIn()`, `whereNotIn()` or `orWhereNotIn()`
tags the table it reads as well. That includes calls by named argument and
calls made inside a `beforeQuery()` callback. `straightJoinSub()` exists on
Laravel 13 only.

For a model whose `newBaseQueryBuilder()` returns its own builder class, such
as one from `staudenmeir/laravel-cte`, the tag is added for calls made through
the model's query and for calls made inside a custom Eloquent builder's own
methods. A subquery on such a model is not tagged in these cases:

- It is added to the base builder directly, through `toBase()`, `getQuery()`
  or a `beforeQuery()` callback.
- A custom Eloquent builder defines its own method with one of those names,
  calls it from another of its own methods, and that method does not call its
  parent. A builder that extends `CachedBuilder` and defines one of those
  methods without calling its parent is not tagged either.
- A custom Eloquent builder registers a local macro with one of those names
  and uses it inside the same method call.
- A custom Eloquent builder calls one of those methods from its own methods in
  a different letter case, such as `whereIN()`. Calls through the model's
  query are matched in any letter case.

After a write to that table, call `ModelCache::invalidate()` for the model that
ran the query.

Joined tables include the pivot or intermediate table of a `belongsToMany`,
`morphToMany`, `hasManyThrough`, or `hasOneThrough` relationship. Writing rows
of that table through its own `Cachable` model therefore invalidates cached
reads of the relationship.

An eager-loaded `belongsToMany` or `morphToMany` relationship tags its pivot
table as well. A write to the pivot table through a `Cachable` model, or through
a `Cachable` custom pivot class, therefore invalidates the query that eager-loads
it. A many-to-many relationship eager-loaded through a `morphTo`, such as
`with('commentable.tags')`, does not tag its pivot table. After a write to that
pivot table outside the relationship, call `ModelCache::invalidate()` for the
model that ran the query.

A pivot `attach`, `detach`, `sync` or `updateExistingPivot` through the
relationship flushes the pivot table's tags itself. That holds with model events
off, and when the related model does not use `Cachable`. An active cool-down
window on either model holds that flush back.

### 🔗 BelongsToMany with Custom Pivot Models
Cache invalidation works for `BelongsToMany` relationships using custom pivot
models (`->using(CustomPivot::class)`) as long as either the parent or the
related model uses the `Cachable` trait.

### 🧹 Manual Cache Flushing

**Artisan command — single model:**
```sh
php artisan modelCache:clear --model='App\Models\Post'
```

**Artisan command — all models:**
```sh
php artisan modelCache:clear
```

> **ℹ️ Scope of the full clear (no `--model`)** — how much is removed depends on the cache driver:
> - **Redis** (any cache-store prefix, including none) — only this package's entries and cool-down keys are deleted, found through the `genealabs:laravel-model-caching:` prefix on every tag and cool-down key it writes. Every other key is left intact, including the application's own entries under the same store prefix. This honors both the connection-level client prefix (`database.redis.options.prefix`) and the cache-store prefix, and works on the phpredis **and** Predis clients, single-node or cluster (`CROSSSLOT`-safe).
> - **DynamoDB** — rotates a package-wide namespace key; no destructive table scan (see the DynamoDB section).
> - **Memcached, file, database, or array** — these cannot scope a clear by prefix, so the **entire** cache store is flushed. Give the model cache a dedicated store (see the *Custom Cache Store* section) so a full clear never touches unrelated data.

**🔧 Programmatic via Facade:**
```php
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;

// Single model
ModelCache::invalidate(App\Models\Post::class);

// Multiple models
ModelCache::invalidate([
    App\Models\Post::class,
    App\Models\Comment::class,
]);
```

### ⏰ Cache Expiration (TTL)
Cached queries are stored indefinitely (`rememberForever`) by default and rely
on automatic invalidation (see above) to stay fresh. This is the right choice
for most applications — Redis and Memcached already manage stale-record
eviction and memory usage on their own, so a blanket expiration setting isn't
needed just to bound memory.

TTL exists as an **opt-in** safety net for the different problem of data
written outside Eloquent entirely — a script, a direct SQL statement, another
process — where nothing ever calls `flushCache()` or the `modelCache:clear`
Artisan command. For a model like that, an expiration bounds how long a stale
read can persist, without you having to remember to invalidate manually on
every external write.

**Global TTL**, via config or environment variable, applies to every cachable
model unless overridden:

```
MODEL_CACHE_TTL=86400
```

**Per-model TTL** overrides the global value for one model. It also works with
no global TTL set at all:

```php
<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class ImportedProduct extends Model
{
    use Cachable;

    protected $cacheTtlSeconds = 3600; // 1 hour ⏱️
}
```

**Valid values.** Both levels follow the same rules:

| Value | Meaning |
| --- | --- |
| not set, `null` or `''` | No TTL at this level. A model falls back to the global TTL. The global level means "cache forever". |
| `0` or `'0'` | Global: no TTL, so cache forever. Per-model: opt this model out of the global TTL, so it caches forever. |
| A positive integer, or a string of digits such as `'3600'` | Expire after that many seconds. |
| Anything else | Rejected. Every query that resolves the value throws an `InvalidArgumentException` that names the model or the config key, and the value. This covers negative numbers, decimals such as `1.9`, strings such as `'60s'` or `'abc'`, and booleans such as `MODEL_CACHE_TTL=true`. |

**Eager loads.** A cached query result holds its eager-loaded models too. So
the query takes the shortest TTL among its root model and every model it eager
loads, nested eager loads (`with('books.publisher')`) and `morphTo` targets
included. Each of those models contributes its own TTL, or the global TTL when
it sets none. A model that contributes no TTL does not lower the result, and a
model opted out with `0` does not force the whole query to cache forever. For
example, `Author::with('books')->get()` expires after 3600 seconds when `Book`
sets `$cacheTtlSeconds = 3600`, even though `Author` sets no TTL. A query with
no TTL at any level keeps the forever-cache behavior unchanged.

`Model::all()` counts the relations in the model's default `$with` as eager
loads, for both its TTL and its cache tags. So a cached `Author::all()` on an
`Author` with `protected $with = ['books']` takes `Book`'s TTL, and an Eloquent
write to a `Book` flushes it.

> **⬆️ Upgrade note:** `all()` entries for models that declare `$with` now carry
> the tags of those relations. Those entries read cold once after you upgrade,
> then cache as before. No cache key or tag format changes.

**Redis.** An expired item leaves its entry behind in each cache tag's set,
because Laravel's Redis tag sets do not expire with the items. Schedule
Laravel's `cache:prune-stale-tags` Artisan command to remove those entries, for
example hourly:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cache:prune-stale-tags')->hourly();
```

TTL and the cool-down period above solve different problems and can be used
together: cool-down debounces how often a *write* flushes the cache, while TTL
bounds how long a value can live when nothing flushes it at all.

### 🧪 Testing
In your test suite you can either disable model caching entirely or use the
`array` cache driver:

**🚫 Disable caching in tests:**
```php
// In your TestCase setUp() or phpunit.xml
config(['laravel-model-caching.enabled' => false]);
```

**✅ Use the array driver** (useful for testing cache behavior itself):
```php
config(['cache.stores.model-test' => ['driver' => 'array']]);
config(['laravel-model-caching.store' => 'model-test']);
```

### 👷 Queue Workers
The package has no special queue or Horizon integration. Cached queries inside
queued jobs work the same as in HTTP requests. Cache invalidation triggered in a
web request is immediately visible to queue workers (assuming a shared cache
store like Redis). No additional configuration is needed.

### 🔍 Static Analysis (Larastan / PHPStan)
The package is compatible with [Larastan](https://github.com/larastan/larastan)
at level 5 and above. With no annotation, the scopes `disableCache()` and
`withCacheCooldownSeconds()` resolve anywhere in a query chain, and
`flushCache()` resolves on a model instance.

A query chain that ends in a `CachedBuilder` method does not. Larastan types
`Post::where(...)` as Eloquent's own builder, so `->flushCache()` and
`->cache()` on it are reported as undefined methods. `CachedBuilder` is not
generic, so naming it as your model's builder type trades these errors for
others: Larastan then no longer finds the scopes above, and query results lose
their model type. Use the calls that resolve instead:

```php
use App\Models\Post;
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;

(new Post)->flushCache();
ModelCache::invalidate(Post::class);
```

Both are also safe with caching disabled, through `MODEL_CACHE_ENABLED=false`
or inside `runDisabled()`: they do nothing there. A chained `flushCache()` or
`cache()` is not. With caching disabled the model returns Eloquent's own
builder, so the chained call throws `BadMethodCallException`. If you suppress
the PHPStan error on a chain in your own project, that runtime case still
applies.

The package's own source carries pre-existing level-5 findings, recorded in
`phpstan-baseline.neon` so CI can enforce the level from here on. That baseline
is internal to this repository and does not affect analysis of your project,
because your own `phpstan.neon` decides which paths are analysed and `vendor/`
is not one of them.

## 🤝 Contributing
Contributions are welcome! 🎉 Please review the
[Contribution Guidelines](https://github.com/GeneaLabs/laravel-model-caching/blob/master/CONTRIBUTING.md)
and observe the
[Code of Conduct](https://github.com/GeneaLabs/laravel-model-caching/blob/master/CODE_OF_CONDUCT.md)
before submitting a pull request.

## ⬆️ Upgrading
For breaking changes and upgrade instructions between versions, see the
[Releases](https://github.com/GeneaLabs/laravel-model-caching/releases) page on
GitHub.

### PHP 8.2 support is dropped

**PHP 8.2 is no longer supported.** Move to PHP 8.3 or later before you take
this release. Composer refuses to install it on PHP 8.2.

The `php` constraint and the CI matrix now start at PHP 8.3. No cache key,
cache tag, or runtime behaviour changes.

### Laravel 11 support is dropped

**Laravel 11 is no longer supported.** Move to Laravel 12 or 13 before you take
this release. Composer refuses to install it on Laravel 11.

Laravel 11 stopped receiving security fixes on 12 March 2026. This package
supports the overlap of the actively maintained Laravel and PHP versions, so
the `illuminate/*` constraints and the CI matrix now name Laravel 12 and 13
only.

This removes leftover compatibility rather than introducing a break. The 13.0
release already moved this package's major line. Laravel 11 was kept past it
instead of being dropped at the time.

PHP 8.2 was unaffected by that release. No cache key, cache tag, or runtime
behaviour changes.

### Carbon bindings are re-keyed once

**Every cached query holding a `Carbon` binding reads cold once after this
upgrade.** No action is needed. The old entries are not read again and expire
on their own TTL.

A `Carbon` binding used to be written into the cache key by Carbon's own
`__toString()`, because `Carbon` is `Stringable`. It is now formatted as
`Y-m-d-H-i-s`, which is what `DateTime` and `DateTimeImmutable` bindings have
always used.

The reason is that `__toString()` is governed by `Carbon::setToStringFormat()`,
a process-global setter any application may call. One such call silently
re-keyed every affected query, orphaning the existing entries with no error
and a cache miss that looked like nothing at all. A Carbon release changing
the default format had the same reach. Formatting the value ourselves removes
that input from the key.

Only `Carbon` bindings are affected. No other cache key or cache tag changes.

## 🔐 Security
Please review the [Security Policy](https://github.com/GeneaLabs/laravel-model-caching/blob/master/SECURITY.md)
for information on supported versions and how to report vulnerabilities.

## 📚 Further Reading
The [test suite](https://github.com/GeneaLabs/laravel-model-caching/tree/master/tests)
serves as living documentation — browse it for detailed examples of every
supported query type, relationship pattern, and edge case. 📖

---

<p align="center">
Built with ❤️ for the Laravel community using lots of ☕️ by <a href="https://github.com/mikebronner">Mike Bronner</a>.
</p>

<p align="center">
This is an MIT-licensed open-source project. Its continued development is made
possible by the community. If you find it useful, please consider
<a href="https://github.com/sponsors/mikebronner">💖 becoming a sponsor</a>
and 
<a href="https://github.com/mikebronner/laravel-model-caching">⭐ing it on GitHub</a>.
</p>

<p align="center">
🙏 Thank you to all <a href="https://github.com/mikebronner/laravel-model-caching/graphs/contributors">contributors</a> who have helped make this package better!
</p>
