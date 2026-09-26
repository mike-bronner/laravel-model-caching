---
paths:
  - 'tests/database/factories/**'
---

# Factories

## Test factory style
Declare `protected $model` and build `definition()` with `$this->faker`, not `fake()`. Pass overrides at the call site instead of adding state methods. A factory for a cached model gets an `Uncached*Factory` twin.
