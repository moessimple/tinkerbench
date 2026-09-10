---
paths:
  - '**/*'
  - composer.json
  - README.md
---

# General

## What tinkerbench is
A local, browser-based PHP REPL for any project linked in Laravel Herd: you write a PHP snippet in the browser, it runs against the target project's own runtime (its database and services attached), and the output comes back as a single chronological feed of cards. It is a personal developer tool that runs only on the developer's own machine, never deployed or exposed (see "Never mutate the developer's saved snippets").

Shape: the repo-root Laravel app is the UI and API (Inertia + Vue, a Monaco editor, the intelephense language server); `packages/runner` is a standalone package that runs each snippet in its own PHP process against the target project. Target support floor is PHP 8.2+, and Laravel 12+ for the query/log/N+1 cards (see "Target project support floor: Laravel 12+ and PHP 8.2+").

## Consistency beats personal style
Consistency (naming, structure, testing style, mocking approach, route conventions) is the top-tier quality bar for this project, above cleverness or terseness. When a stylistic choice is ambiguous, check how the nearest comparable case was already solved in this codebase before deciding, don't default to personal preference. Small deviations (a leading slash on a route, `test()` vs `it()`, a test name that leaks an internal collaborator name) are worth fixing, not "functionally identical, good enough".

## Full, isolated test coverage is mandatory, no silently invented exceptions
Every new/changed class in app/Actions|Support|Enums needs its own isolated unit test proving its behavior (see app.md); a new/changed Controller needs its own Http flow test instead, and a new/changed Request/Middleware needs its own tests/Unit/ test (see tests.md), with documented exceptions for pure framework-override glue that carries no app-specific logic (e.g. HandleInertiaRequests, see middleware.md). Every new/changed Vue component or JS module needs its own test too. This is mandatory, applies equally to PHP and JS/Vue, and includes plain enums, thin controllers, and anything else that looks "too small to test": don't invent an ad hoc exception (e.g. skipping a value-only enum's test) without flagging it to the user first and getting confirmation.

How to keep that coverage isolated and non-duplicated (mocking, one behavior per test, not re-proving a lower layer) is defined in tests.md for PHP and js.md for JS/Vue, follow those. When two sibling classes share a shape (e.g. two FormRequest-backed controllers), give them symmetric coverage.

## Never mutate the developer's saved snippets
The `.php` files under `storage/app/snippets/**` are the developer's own data, not source or fixtures. Do not
create, rename, delete, or edit an existing snippet, whether by editing the file, calling the
`/api/projects/{project}/snippets/...` write endpoints, or driving the running app to run/save/rename/delete one
(running a snippet autosaves it). For verification, prefer `POST /api/projects/{project}/snippets/executions`
with an inline `code` body — it runs arbitrary PHP and returns the feed without persisting anything. Full rule,
exceptions (an explicitly named snippet in a task; the `agent-scratch` sandbox) and recovery steps: snippets.md.

## No final classes, anywhere
No class in the app is `final`. It blocks Mockery from creating a class double ("cannot override methods of a final class"), which forces awkward workarounds when a test needs to mock a class directly. Enforced project-wide by tests/ArchTest.php.

A `readonly` **class** has the same problem: Mockery can't extend it, so any class mocked directly in a test (an Action, a Support class with behavior) stays a plain `class`. Property-level `readonly` on promoted constructor properties is fine, it doesn't block mocking (Mockery skips the constructor), and Rector adds it automatically (`ReadOnlyPropertyRector`) while never promoting the class itself (`ReadOnlyClassRector` is skipped, see rector.php). A plain DTO/value object that's never mocked, only constructed with real values, may be a `readonly` class.

## Don't keep single-implementation interfaces for mockability
Now that classes aren't final (see "No final classes, anywhere"), an interface with exactly one implementation and no second caller has no reason to exist just to enable mocking, mock the concrete class's own leaf dependency instead: App\Actions\RunSnippetAction depends on App\Support\Herd directly, no wrapping interface, since Herd is mockable on its own. Before adding an interface "for testability", check whether the class it would wrap is even blocked from direct mocking.

## Use composer test as the final verification gate
Before considering a change done (committing, closing out a task/checkpoint), run `composer test` rather than manually chaining `pint`, `phpstan`, `pest --type-coverage`, and `pest --coverage` as separate commands — it already runs exactly that sequence (see composer.json's `test`/`test:*` scripts) in one shot and is less error-prone than reassembling it by hand each time.

This doesn't replace fast, targeted `php artisan test --compact --filter=X` runs while actively iterating on one piece (still the right tool for quick RED/GREEN feedback) — it's the gate before calling the work finished.

## Dead-code sweeps: don't flag framework/starter-kit scaffolding
When hunting for dead code, a file having no caller is not enough to call it dead if it ships as Laravel/Pest/starter-kit scaffolding. Keep these even with zero references:

- app/Http/Controllers/Controller.php - Laravel skeleton base controller (nothing extends it, that's fine).
- tests/Pest.php: expect()->extend('toBeOne', ...) and function something() - Pest --init scaffolding.
- Auth baseline, even though there is no login route or auth middleware: app/Models/User.php, UserFactory, tests/Unit/Models/UserTest.php, config/auth.php, the create_users_table migration, the auth.user share in HandleInertiaRequests, resources/js/types/auth.ts.

Only propose removing code that was written for this app's own logic and lost its last caller.

## Never remove config.autoloader-suffix (root and packages/runner)
composer.json pins `config.autoloader-suffix` to `TinkerbenchInternal`; `packages/runner/composer.json` pins its own to `TinkerbenchRunner`. `packages/runner/bin/run-snippet.php` loads the runner package's own vendor/autoload.php and then the target project's vendor/autoload.php in the same PHP process (see `Tinkerbench\Runner\SnippetRunner::run()`). Both define a class named ComposerAutoloaderInit<suffix>; without a pinned, distinctive suffix, a target project scaffolded from the same starter kit as tinkerbench (or sharing Composer's default suffix algorithm's result) can end up with the identical generated suffix, and running a snippet against it fatals with "Cannot redeclare class ComposerAutoloaderInit...". Fixing this in the target project isn't an option (tinkerbench must run against any Herd-linked project unmodified), so both suffixes are pinned instead, each unique. Never remove either or let them drift without keeping them unique from each other and from a target project's likely default.

## Target project support floor: Laravel 12+ and PHP 8.2+
Supported target projects: PHP 8.2 or newer, and for the Laravel feed (query/log/N+1 cards) Laravel 12 or newer. The Laravel 12 floor is a support policy: Laravel 11 no longer receives security fixes. Not enforced in code (Herd::resolveLaravelVersion only distinguishes "is it Laravel at all"), so it lives in docs.

In README/user-facing text state it as a plain requirement ("Laravel 12 or newer"). Do not phrase it as "tested against 12 and 13" (understates it) and do not spell out the security-EOL reason.

## Themed test:* aliases cover app + packages/runner + frontend
Each `composer test:*` alias runs its theme across every codebase, mirroring `lint`:
`test:lint`, `test:types`, `test:unit` each run their PHP checks over app/, then
`composer <same> --working-dir=packages/runner`, then the frontend `npm run <same>`.
`test:type-coverage` is PHP-only (Pest's type-coverage gate over app/ and packages/runner);
there is no `npm run test:type-coverage`.
There is no `test:runner` / `test:php82` bundle: the runner's lint/static/type-coverage
belong to the matching theme, not to a tests job.

CI mirrors this one theme per workflow: lint.yml = test:lint + test:type-coverage,
static.yml = test:types, tests.yml = test:unit (+ browser). tests.yml `runner (8.2)` is
the only place the runner suite runs on a real PHP 8.2 interpreter; it calls
`composer test:unit:no-coverage --working-dir=packages/runner` directly (static checks are
interpreter-independent and already run on 8.5). Both lint.yml and static.yml must install
packages/runner's Composer deps because those aliases now shell into it.

## Keep the README in sync with user-visible changes, and fact-check it
When a change alters user-visible behavior, setup steps, requirements, or the feature set, update README.md in the same change. Verify concrete claims against source, not memory: keyboard shortcuts and command-palette prefixes in resources/js, the slow-query threshold in packages/runner/src/FeedItems/QueryFeedItem.php (SLOW_THRESHOLD_MS), what `composer setup` and `composer test` actually do in composer.json's scripts, and the PHP/Laravel version floors (see "Target project support floor: Laravel 12+ and PHP 8.2+"). Rewording or polishing the prose is not the same as checking it; the verification is a separate step.
