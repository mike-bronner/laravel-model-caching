---
paths:
  - 'tests/**'
---

# Tests

## Base test case
Extend `GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase` in every test, Feature tests included. Read the cache through `$this->cache()`, which deserializes stored values, not through the `Cache` facade or `cache()`.

## Test layout
Put tests in `tests/Integration`, in a folder that follows the `src/` path of the code under test. Put query-builder behaviour in `CachedBuilder/<Feature>Test.php`. Use `tests/Feature` only for HTTP-rendered behaviour.

## Declare return types in tests
Always declare a return type on every test method (`: void`) and on every helper method in a test class.
