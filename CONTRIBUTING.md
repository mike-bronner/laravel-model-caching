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
- [ ] Make sure there are no build errors on [GitHub Actions](https://github.com/mike-bronner/laravel-model-caching/actions).

### What CI enforces
The test suite, across PHP 8.2 to 8.5 and Laravel 11 to 13. Nothing else.

### Static analysis and code style
PHPStan, Pint and PHPCS are all configured in this repo. None of them runs in
CI, and the tree does not currently satisfy any of them:

| Tool | Command | State |
| --- | --- | --- |
| PHPStan (level 5) | `composer analyse` | 978 errors |
| Pint | `vendor/bin/pint --test` | 210 files |
| PHPCS | `vendor/bin/phpcs` | 593 errors in 24 files |

So do not expect a clean run from any of them, and do not treat a red result
as something your change caused. Run one if it helps you, and read only the
lines that name the files you touched.

Match the style of the code around your change rather than reformatting to
satisfy a tool. A reformatting pass is welcome as its own pull request, and
unwelcome mixed into a behavioural one, because it buries the actual change.

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
