# Instructions for AI coding agents

This file is the entry point for any AI coding agent working in this
repository. It is procedure: how to set up, what to run, and when to stop.

`CONTRIBUTING.md` is the authority on what this project expects of a change.
Read it. Where this file and `CONTRIBUTING.md` cover the same ground,
`CONTRIBUTING.md` wins and this file is a restatement for convenience.

## How this file is maintained

This file has two halves, and they have different owners.

Everything you are reading now is the hand written half. It is the project's
own rules, and no tool produces it.

Laravel Boost writes the other half. It wraps what it generates in a
laravel-boost-guidelines block, appended at the end of this file the first time
and replaced in place after that. The replacement touches that block and
nothing else, so the hand written half survives every regeneration.

Never write that block's opening tag anywhere in the hand written half, not
even quoted as an example. Boost replaces from the first opening tag it finds
to the end of the file, so a tag in the prose makes it delete everything below,
which is how the hand written half was lost once already. The block is named
here without its angle brackets for that reason.

That tells you where to edit. Change the hand written half here. Never change
anything inside the generated block, because the next compose discards it. The
block is composed from the `.ai/guidelines` directory, so a change to the
generated half belongs there.

`.ai/guidelines` is not tracked here, and version control ignores it. The
shared settings package installs it, and the Laravel Boost section below covers
how that works.

One consequence of that follows from it. The block is composed from whatever
`.ai/guidelines` holds on the machine that ran the command, so read a generated
block before you commit it. Guidance that belongs to another project reaches
this file the same way the right guidance does.

## Which half wins

The hand written half wins. Where the two halves disagree, follow the hand
written half and do not weigh the two against each other.

The reason is what each half knows. The hand written half was written about
this repository. The generated block is composed from a guideline set that
lives on the machine rather than in this project, so it describes the common
case, and the common case is a Laravel application. This repository is not
one. Wherever the generated block assumes an application, it is wrong here,
whatever it happens to say on the day you read it.

That test is the durable one, so apply it rather than the list below. The list
describes the block as it stands today and it will not survive the next
compose.

As this is written, the generated block carries Laravel's stock application
guidance instead of this project's own, because a gating problem in the shared
settings package stopped the project set from composing. While that is still
true, expect the block to contradict the hand written half in these ways.

- It opens by calling this project a Laravel application. It is a package. See
  "What this repository is" below.
- It tells you to run `php artisan` for several different commands. There is
  no such binary here. Use `vendor/bin/testbench`.
- It tells you to use `php artisan tinker`. There is no tinker binary and no
  tinker tool on the MCP server. The Laravel Boost section below lists the
  tools that server actually returns.
- It covers frontend bundling and tells you to ask about `npm run build`.
  There is no frontend here and no `package.json`.
- It covers deploying to Laravel Cloud. A package is released, not deployed.

Check that list against the block before you lean on it. If the block no
longer says these things, the gating problem has been fixed and the list is
stale. The rule above it is not.

## What this repository is

This is a Laravel **package**, not a Laravel application.

That single fact invalidates most command lines copied from Laravel's own
documentation. There is no `artisan` binary at the repository root, no
`bootstrap/app.php`, and no application config directory. A console command
that a Laravel application would reach through `php artisan` is reached here
through the Orchestra Testbench console binary that Composer installs:

```
vendor/bin/testbench <command>
```

Do not write `php artisan` anywhere in this repository and do not tell a
developer to run it. It will fail.

## Setup

`composer.lock` is not committed, so there is no `composer install` path.

```
composer update
```

That resolves and installs everything, including the dev tooling this file
refers to.

## Laravel Boost

Laravel Boost is this project's documentation and live-state tool. It is a dev
dependency of the package, so `composer update` brings it in.

### The guidelines compose themselves

`mikebronner/development-settings` is also a dev dependency, and it is a
Composer plugin. After every install and update it composes the Boost
guidelines into this file, through a runner it ships for package repositories
like this one. There is nothing to configure and nothing to run by hand.

The runner does the whole job itself. It boots an application rooted at this
directory in process, so no configuration file has to relocate the base path.
It creates the framework directories that boot needs, so none of them are
tracked here. It registers Boost's commands explicitly, because Boost otherwise
registers them only when the console is running. It writes `boost.json` from
the agents it detects when that file is absent.

`boost.json` and everything else Boost writes are per-developer and listed in
`.gitignore`, so your own tool configuration is never committed and never
overwritten by anyone else's. `AGENTS.md` and `CLAUDE.md` are the exceptions
and stay tracked.

Do not run `boost:install` yourself to get guidelines. This repository is a
package and has no application base path of its own, so the installer resolves
one from the testbench skeleton inside `vendor` and composes from there rather
than from here. `composer update` is the path that reaches this repository, and
it runs on its own.

### Register the MCP server with your agent

The MCP server is the one part you still set up by hand, because the plugin
does not touch your agent's configuration. Confirm the server answers first:

```
printf '%s\n{"jsonrpc":"2.0","method":"notifications/initialized"}\n{"jsonrpc":"2.0","id":2,"method":"tools/list"}\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"c","version":"1"}}}' \
  | vendor/bin/testbench boost:mcp
```

That same command line, without the `printf` pipe, is the one to put in your
agent's MCP server configuration. Boost's own installer writes `php artisan
boost:mcp` instead, and that binary does not exist in a package. Use the
testbench form above.

A working server returns an `initialize` result naming `Laravel Boost` and then
lists ten tools:

`application-info`, `browser-logs`, `database-connections`, `database-query`,
`database-schema`, `get-absolute-url`, `last-error`, `read-log-entries`,
`record-rule`, `search-docs`.

There is no `tinker` tool in that list. Do not reach for one.

### Use `search-docs` for framework questions

`search-docs` returns version-correct documentation for the packages actually
installed here. Use it for any question about Laravel, Eloquent, the cache
contracts, or PHPUnit behaviour.

### Stop if Boost is unreachable

If `search-docs` is not available to you, **stop and report it**. Do not fall
back to grepping `vendor/`.

The reason is that the two are not substitutes. Grep reads source text. Boost
reads the live, resolved state of this installation and returns documentation
matched to the versions in it. A silent fall back to grep produces answers that
look sourced and are not, which is the failure Boost exists to prevent. Say the
tool is missing and let the setup be fixed.

## Operating modes

Agents work here in one of two modes. The rules are the same in both. What
differs is what you do when you reach a stop condition.

**Supervised.** A developer is present and reading your output. Raise the
question with them and wait for an answer before you continue.

**Unattended.** You are part of an autonomous team with no one to ask. Record
the blocker where the work is tracked, which is the issue or pull request you
are working from, and stop. Do not resolve the question yourself and do not
continue past it on an assumption.

Neither mode guesses.

## Stop conditions

Reach one of these and you stop, in the mode's own way.

- **Laravel Boost is unreachable.** Covered above.
- **The change alters the package's public API.** Anything a consuming
  application can call, extend, or configure. This package is installed by
  other people's applications, so a signature change is their breakage.
- **The change alters a cache key or a cache tag format.** Cache keys are the
  sharp edge here. A key format change makes every consumer read cold for those
  queries after they upgrade, which is a release-note decision rather than an
  implementation detail.
- **The test suite will not go green.** Do not delete a failing test, do not
  mark it skipped, and do not weaken an assertion to get past it. Report the
  failure instead.
- **The change would add new entries to the PHPStan baseline.** The baseline is
  a record of debt that already exists, not a place to put new debt. See below.

## Running the checks

### Tests

```
composer test
```

The suite requires a **reachable Redis** on the default port, because Redis is
the cache store it runs against. A Redis that is not running is the usual cause
of a wall of failures that have nothing to do with your change.

Run one test while you iterate:

```
vendor/bin/phpunit --configuration phpunit.xml.dist --filter <testName>
```

Before you trust a test you wrote for a fix, revert the fix and confirm the
test goes red. A test written after a fix very often passes without it, and
such a test guards nothing. `CONTRIBUTING.md` and the pull request template
both ask for this, and it is the step most often skipped.

### Static analysis

```
composer analyse
```

PHPStan runs at level 5. `phpstan.neon` includes `phpstan-baseline.neon`, which
records the pre-existing findings so that the level can be enforced from here
on. A clean run therefore means your change introduced nothing new.

Treat the baseline as a debt ledger and not as permission. If you fix a finding
in a file you are already touching, delete its entry. Never add an entry to
silence a finding your own change introduced, and never regenerate the whole
baseline to make a run go green, because that absorbs the new finding along
with the old ones.

### Code style

```
vendor/bin/pint --test
vendor/bin/phpcs src tests
```

Neither is clean on this tree and neither runs in CI. A red result from either
is almost certainly pre-existing and almost certainly not caused by your
change. `CONTRIBUTING.md` lists every code-quality tool this project
configures, with the command for each and the state it is in. Read only the
lines that name the files you touched.

Match the style of the code around your change rather than reformatting to
satisfy a tool. A reformatting pass is welcome as its own pull request and
unwelcome mixed into a behavioural one, because it buries the actual change.
`CONTRIBUTING.md` has the coding conventions themselves, including four-space
indentation and the spacing rules.

## Commits and pull requests

Commit subjects follow Conventional Commits with a Gitmoji, as the history
shows:

```
fix: 🐛 Build a cache key for a DateTimeImmutable binding
test: ✅ Harden ofMany cache-key coverage
```

Run `git log --oneline` to see the convention in use before you write one.

Keep a commit to one coherent change. Do not mix a reformatting pass into a
behavioural one.

`.github/PULL_REQUEST_TEMPLATE.md` sets out what a pull request body must
contain. Fill it in rather than replacing it, and say plainly if your change
touches a cache key.

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
