---
paths:
  - 'tests/Integration/**'
---

# Integration

## Test method naming
Name test methods in camelCase with a `test` prefix (`testCachedResultMatchesLiveQuery`). Do not use snake_case or the `#[Test]` attribute.

## No database reset trait
The base test case gives every test a fresh copy of the seeded SQLite baseline, so do not add `RefreshDatabase` or similar traits. Use `RefreshDatabase` only in classes that switch to a real PostgreSQL connection.

## Test data comes from the seeded baseline
Read existing seeded rows (authors, books, stores and their uncached twins) where possible. Create extra rows with the model's factory. Do not call `$this->seed()` in a test.

## Real stores, hand-written doubles
Test against the real Redis cache and SQLite database. When a failure path needs a stand-in store, connection or exception, write a named class in `tests/Fixtures` (`Fake*`, `Throwing*`) instead of a Mockery or PHPUnit mock.

## No data providers
Write one named test method per case, and put shared setup or assertions in private helper methods on the test class. Do not use data providers.

## Compare against the uncached twin
Prove a cached result by running the same query on the model's `Uncached*` twin, then compare with `assertEquals` on plucked IDs or `diffKeys`. Name the variables `$cached*` and `$live*`.

## Spell out expected cache tags
Write expected cache tags as literal strings on the `genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:` prefix and read through `$this->cache()->tags($tags)->get($key)`. Do not derive them from the package's own tag builder. To prove invalidation, re-read the same key after the write and `assertNull`.

## Literal keys pin the format, the helper compares keys
When a test pins the exact cache key, write it as a literal `sha1("genealabs:laravel-model-caching:testing:...")` string. When a test only proves that two queries get different keys, or checks a key fragment, build keys through the private reflection `cacheKey()` helper that calls `makeCacheKey`.

## Start queries from a model instance
Start test queries with `(new Model)->…` rather than static `Model::query()` or `Model::where()`.

## Explain tests with line comments
When a test needs explanation, write `//` line comments above the class or the test method that state the mechanism or bug being pinned. Do not use PHPDoc blocks.
