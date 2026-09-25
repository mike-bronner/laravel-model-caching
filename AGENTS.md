# Instructions for AI coding agents

This file is for an AI coding agent that contributes to this repository. It
covers what the package is, the standards a change must meet, and the process
from issue to review.

`CONTRIBUTING.md` is the authority on what the project expects of any change.
This file adds what an agent needs on top of it.

## What this package is

This package caches Eloquent query results and invalidates them from model
events.

It is a Laravel **package**, not a Laravel application. Other people's
applications install it, so their code depends on how it behaves.

Cache keys and cache tags are its contract with those applications. A new key
format makes every consumer read cold for those queries after they upgrade. A
new tag format changes which cached results an invalidation reaches.

### Run console commands through `vendor/bin/testbench`

There is no `artisan` file in this repository, so `php artisan` fails here.
Orchestra Testbench ships a console that stands in for it:

```
vendor/bin/testbench <command>
```

Laravel's documentation and the generated block at the end of this file both
say `php artisan`. Console commands that inspect the application run through
testbench instead, as in `vendor/bin/testbench config:show app.name`. They
report on the application testbench boots, which is not a real one.

The `make:` generators do not work here. `make:class` writes into
`workbench/app/` under the `Workbench\App` namespace, not into `src/`.
`make:test` fails with an error. So create each new file by hand, next to its
siblings, and copy their structure. That keeps it in the package's namespace
and layout.

Run tests with `composer test`, or with `vendor/bin/phpunit` exactly as shown
under "Run the checks". Both pass this repository's `phpunit.xml.dist`, so the
suite runs as CI runs it.

The generated block is written for Laravel applications. Where it disagrees
with this file, this file wins, because this file is written for this package.

Do not edit the generated block. `composer update` rewrites it, so an edit
there is lost.

## Setup

The developer runs setup, not you:

```
composer update
```

`composer.lock` is not committed, so there is no `composer install` path. The
update installs the dev tooling. It then runs the Laravel Boost installer,
which composes the Laravel Boost guidelines into the end of this file.

Never run `composer update` yourself. Tell the developer that it needs running,
and wait until they confirm it has finished. The installer asks the person at
the terminal which features and agents to set up. Run by an agent, it asks
nothing and takes its defaults. Those defaults are not the developer's
choices.

Never commit a change to the generated block. Its content depends on the Boost
version installed and on the answers given to the installer. So a change there
comes from your setup, not from your fix. Leave it unstaged, because it would
be a drive-by change in your pull request.

## Laravel Boost is required

Answer every question about Laravel, Eloquent or the cache contracts with
Boost's `search-docs` tool. It searches Laravel's hosted ecosystem
documentation. Memory stops at your training date, so it can describe APIs that
have since changed.

`search-docs` searches every version of that documentation at once. It cannot
see this repository's dependencies, so its `packages` filter has no effect
here. Each result carries a `package@version` label. Use the results for the
major versions that `composer.json` supports. Write code that runs on each of
those majors, because CI tests every one. An API that exists in only one major
breaks consumers on the others. `composer show <package>` reports the version
installed locally.

Register the Boost MCP server with your agent. This is the entry for Claude
Code's `.mcp.json`. Other agents take the same command and arguments:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["vendor/bin/testbench", "boost:mcp"]
        }
    }
}
```

Boost's own installer writes `php artisan boost:mcp` as the command. That fails
here for the reason above, so replace it with the entry shown.

**If Boost is unreachable, stop.** Tell the developer that `composer update`
needs running and that the server needs registering as shown. Then wait for
them to confirm both. Do not run the update yourself, for the reason under
"Setup". Do not fall back to grepping `vendor/`, because source text is not
documentation.

## Development standards

### Prove each new test red, then green

Revert your fix and run the new test. Confirm that it fails. Then restore the
fix and confirm that it passes. A test written after its fix often passes
without it, and such a test guards nothing.

### Run the checks

```
composer test
composer analyse
```

`composer test` needs a reachable Redis on the default port, because Redis is
the cache store the suite runs against. To run one test while you iterate:

```
vendor/bin/phpunit --configuration phpunit.xml.dist --filter <testName>
```

### Treat the PHPStan baseline as a debt ledger

`composer analyse` runs PHPStan at level 5 against `phpstan-baseline.neon`.
The baseline records findings that existed before, so a clean run means your
change added nothing new.

- If you fix a finding in a file you touch, delete its entry. The ledger then
  shrinks as the code improves.
- Never add an entry for a finding your change introduced. That records new
  debt as old debt.
- Never regenerate the whole baseline. That absorbs your new findings with the
  old ones.

### Match the style of the surrounding code

Copy the style of the code around your change. Do not reformat to satisfy Pint
or PHPCS. Neither passes on this tree, and a reformat mixed into a behavioural
change buries that change. `CONTRIBUTING.md` lists the coding conventions and
the state of each tool.

### Stop conditions

Stop if your change would do any of these:

- **Change the public API.** That is anything a consuming application can
  call, extend or configure. A signature change breaks their code.
- **Change the format of a cache key or a cache tag.** Every consumer's cache
  changes on upgrade, so this is a release decision.
- **Leave the suite red.** Do not delete, skip or weaken a failing test,
  because that hides the failure.
- **Add an entry to the PHPStan baseline.** The baseline is for debt that
  already exists.

When you reach one, post the question in the linked issue and stop. These are
the maintainer's decisions, so do not make them yourself.

## Process

Follow these steps in order.

1. **Start from an issue.** Every pull request links an issue that exists
   before it. Open one first if you need to. The issue is where the maintainer
   agrees that the change is wanted.
2. **Name the branch `fix/<issue>-<slug>`**, for example
   `fix/625-cache-tag-gaps`. The number ties the branch to its issue.
3. **Keep to one issue per pull request.** Change only what the issue
   describes. Drive-by fixes make the review harder, so open a new issue for
   each one instead.
4. **Write commits in Conventional Commits with a Gitmoji**, as in
   `fix: 🐛 Build a cache key for a DateTimeImmutable binding`. Run
   `git log --oneline` to see the format. The type tells a reader what kind of
   change each commit is. The Gitmoji makes the log quick to scan. Keep each
   commit to one change, so the history reads as a list of changes.
5. **Open the pull request from `.github/PULL_REQUEST_TEMPLATE.md`.** Fill in
   every section. `gh pr create --body` and `--fill` skip the template, so fill
   in a copy and pass it with `--body-file`. Open it ready for review, not as a
   draft, and request a review from @mikebronner. A draft asks nobody to look
   at it.
6. **Show the red-then-green proof in the pull request.** Paste the test output
   from the unfixed code and from the fixed code. The reviewer cannot see your
   local run.
7. **Answer every review comment in its own thread.** Reply with the commit
   that fixes it, or with the reason you did not change it. Push new commits on
   top. Never force-push over reviewed commits, because that erases what the
   reviewer read.

## Disclosure

The pull request states that an AI agent wrote it. A named human attests that
they read the diff and ran the suite. The template has a section for both.

The reviewer needs to know how the change was made. A person also has to stand
behind it, because an agent cannot answer for its work after the session ends.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

</laravel-boost-guidelines>
