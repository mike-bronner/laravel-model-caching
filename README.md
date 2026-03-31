# 🚀 Model Caching for Laravel

[![Laravel Package](https://github.com/mikebronner/laravel-model-caching/workflows/Laravel%20Package/badge.svg?branch=master)](https://github.com/mikebronner/laravel-model-caching/actions?query=workflow%3A%22Laravel+Package%22)
[![Packagist](https://img.shields.io/packagist/dt/GeneaLabs/laravel-model-caching.svg)](https://packagist.org/packages/genealabs/laravel-model-caching)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/mikebronner/laravel-model-caching/master/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/mikebronner/laravel-model-caching)](https://packagist.org/packages/mikebronner/laravel-model-caching)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20)](https://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/mikebronner/laravel-model-caching)](https://packagist.org/packages/mikebronner/laravel-model-caching)
[![GitHub Stars](https://img.shields.io/github/stars/mikebronner/laravel-model-caching)](https://github.com/mikebronner/laravel-model-caching/stargazers)
[![codecov](https://codecov.io/gh/mikebronner/laravel-model-caching/graph/badge.svg?token=ACk1Kk4OLO)](https://codecov.io/gh/mikebronner/laravel-model-caching)
[![Tests](https://img.shields.io/badge/tests-335%2B-brightgreen)](https://github.com/mikebronner/laravel-model-caching/tree/master/tests)

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
8.2–8.5 and Laravel 11–13.

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

### 🚫 What Does Not Get Cached
- Lazy-loaded relationships — only eager-loaded (`with()`) relationships are cached. Use `with()` to benefit from caching.
- Queries using `select()` clauses — custom column selections bypass the cache.
- Queries inside transactions — cache is not automatically flushed when a transaction commits; call `flushCache()` manually if needed.
- `inRandomOrder()` queries — caching is automatically disabled since results should differ each time.

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
- PHP 8.2+
- Laravel 11, 12, or 13

## 📦 Installation
```
composer require genealabs/laravel-model-caching
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

Practical guidance:
- Always enable TTL on `expires_at`.
- Treat DynamoDB cache invalidation as logical invalidation first and physical cleanup later.
- Expect dead query rows to accumulate temporarily on write-heavy or high-churn models.
- If you need faster physical cleanup than Laravel's long-lived cache TTL allows, Redis is usually a better fit.

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

Once activated, writes during the cool-down window will not flush the cache.
After the window expires, the next write triggers a flush and re-warms the
cache. 🔄

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
| `increment` / `decrement` | Flush model cache |
| `insert` / `update` (builder) | Flush model cache |
| `truncate` | Flush model cache |

Cache tags are generated for the primary model, each eager-loaded relationship,
joined tables, and morph-to target types, so only the relevant entries are
invalidated. 🎯

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
Cached queries are stored indefinitely (`rememberForever`) and rely on automatic
invalidation (see above) to stay fresh. There is no per-query TTL option. If you
need time-based expiry, use the cool-down period feature or flush the cache on a
schedule via the Artisan command.

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
at level 5 and above. Because the `Cachable` trait wraps Eloquent's builder,
PHPStan may report "undefined method" errors for methods like `cache()` or
`flushCache()` on your models. To resolve these, add a `@mixin` annotation to
your cached model:

```php
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \GeneaLabs\LaravelModelCaching\CachedBuilder<\Illuminate\Database\Eloquent\Model>
 */
class Post extends Model
{
    use Cachable;
}
```

If you use a **custom Eloquent builder** that gets wrapped by `CachedBuilder`,
PHPStan cannot infer the custom methods from the `CachedBuilder` return type.
Add a `@return` override annotation on your model's `newEloquentBuilder()`
method, or add `@mixin YourCustomBuilder` to the model class.

The package ships with a `phpstan-baseline.neon` that suppresses internal
analysis errors in the package's own test fixtures. These do not affect
consumer projects.

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
