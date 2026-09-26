---
paths:
  - 'src/Traits/**'
---

# Traits

## Extend Eloquent through new* factory methods
To cache a new relation or builder type, override the model's `new<Relation>`/`newEloquentBuilder` factory in the `ModelCaching` trait. Return the `Cached*` class only when caching applies, otherwise fall back to `parent`. Do not register macros.

## Cached read methods follow one template
Override a read method by returning `parent::` when `! $this->isCachable()`, building the key with `$this->makeCacheKey(..., "-<op>_<args>")`, and returning `$this->cachedValue(func_get_args(), $cacheKey)`.
