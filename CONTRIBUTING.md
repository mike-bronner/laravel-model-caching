# How to contribute
We welcome everyone to submit pull requests with:
- fixes for issues
- change suggestions
- updating of documentation

However, not every pull request will automatically be accepted. I will review each carefully to make sure it is in line with the direction I want the package to continue in. This might mean that some pull requests are not accepted, or might stay unmerged until a place for them can be determined.

## Testing
- [ ] After making your changes, make sure the tests still pass: `composer test`. A reachable Redis is required, because it is the cache store the suite runs against.
- [ ] When adding new functionality, also add new tests.
- [ ] When fixing errors, write and satisfy new unit tests that replicate the issue.
- [ ] Check that your test actually guards the fix. Revert the change, confirm the test goes red, then restore it. A test written after a fix often passes without it.
- [ ] Run `composer analyse` and make sure PHPStan reports no new errors.
- [ ] Make sure there are no build errors on [GitHub Actions](https://github.com/mike-bronner/laravel-model-caching/actions).

### What CI enforces
The test suite and PHPStan. `phpstan-baseline.neon` records the errors that
already existed when the check was added, so only new ones fail the build.
Adding to that baseline to turn a red build green defeats the point of having
it — fix the error instead, or say in the pull request why it cannot be fixed.

Pint and PHPCS are configured in this repo but are **not** enforced anywhere,
and the tree does not currently satisfy either. Match the style of the code
around your change rather than reformatting to satisfy a tool.

## Submitting changes
When submitting a pull request, it is important to make sure to complete the following:
- [ ] Add a descriptive header that explains in a single sentence what problem the PR solves.
- [ ] Add a detailed description explaining the change and why it's needed.
- [ ] Explain why you think it should be implemented one way vs. another, highlight performance improvements, etc.

## Coding conventions
Start reading our code and you'll get the hang of it. We optimize for readability:
- indent using four spaces (soft tabs)
- ALWAYS put spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`), around operators (`x += 1`, not `x+=1`), and around hash arrows.
- this is open source software. Consider the people who will read your code, and make it look nice for them. It's sort of like driving a car: Perhaps you love doing donuts when you're alone, but with passengers the goal is to make the ride as smooth as possible.
- emphasize readability of code over patterns to reduce mental debt
- always add an empty line around structures (if statements, loops, etc.)

Thanks!
Mike Bronner, GeneaLabs
