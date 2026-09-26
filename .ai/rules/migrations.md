---
paths:
  - 'tests/database/migrations/**'
---

# Migrations

## Test migration style
Write test migrations as named classes extending `Migration`, with `increments('id')` and foreign keys declared as `unsignedInteger(...)` plus `->foreign()->references()->on()`. Omit `down()`.
