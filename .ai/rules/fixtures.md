---
paths:
  - 'tests/Fixtures/**'
---

# Fixtures

## Real stores, hand-written doubles
Test against the real Redis cache and SQLite database. When a failure path needs a stand-in store, connection or exception, write a named class in `tests/Fixtures` (`Fake*`, `Throwing*`) instead of a Mockery or PHPUnit mock.

## Every cached fixture has an uncached twin
A new cached fixture model gets an `Uncached*` twin that omits `Cachable` and sets `$table` to the cached model's table, so tests can compare cached and live results.

## Fixture variants reuse seeded tables
Model a new behaviour as a `<Model>With<Behavior>` class that sets `$table` to an existing seeded table, usually by extending the base fixture. Do not add a migration for a variant.

## Fixture model style
Write fixture models in the legacy forms: the `$casts` property, `getXxxAttribute()` accessors and `scopeXxx()` methods, not `casts()`, the `Attribute` class or `#[Scope]`. A model with `HasFactory` overrides `newFactory()` to return its factory from `Tests\Database\Factories`.
