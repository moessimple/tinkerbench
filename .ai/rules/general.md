---
paths:
  - '**/*'
---

# General

## Consistency beats personal style
Consistency (naming, structure, testing style, mocking approach, route conventions) is the top-tier quality bar for this project, above cleverness or terseness. When a stylistic choice is ambiguous, check how the nearest comparable case was already solved in this codebase (or a cited reference project) before deciding, don't default to personal preference. Small deviations (a leading slash on a route, `test()` vs `it()`, a test name that leaks an internal collaborator name) are worth fixing, not "functionally identical, good enough".

## Full, isolated test coverage is mandatory, no silently invented exceptions
Every new/changed class in app/Actions|Support|Enums needs its own isolated unit test proving its behavior (see app.md); a new/changed Controller needs its own Http flow test instead, and a new/changed Request/Middleware needs its own tests/Unit/ test (see tests.md), with documented exceptions for pure framework-override glue that carries no app-specific logic (e.g. HandleInertiaRequests, see middleware.md). Every new/changed Vue component or JS module needs its own test too. This is mandatory, applies equally to PHP and JS/Vue, and includes plain enums, thin controllers, and anything else that looks "too small to test": don't invent an ad hoc exception (e.g. skipping a value-only enum's test) without flagging it to the user first and getting confirmation.

How to keep that coverage isolated and non-duplicated (mocking, one behavior per test, not re-proving a lower layer) is defined in tests.md for PHP and js.md for JS/Vue, follow those. When two sibling classes share a shape (e.g. two FormRequest-backed controllers), give them symmetric coverage.

## No final classes, anywhere
No class in the app is `final`. It blocks Mockery from creating a class double ("cannot override methods of a final class"), which forces awkward workarounds when a test needs to mock a class directly. Enforced project-wide by tests/ArchTest.php.

Readonly classes/properties are fine: they only cause the same Mockery problem (cannot extend a readonly class) on a class you actually need to mock directly in a test, e.g. an Action or a Support class with behavior. A plain DTO/value object that's never mocked, only constructed with real values, has no reason to avoid `readonly`.

## Don't keep single-implementation interfaces for mockability
Now that classes aren't final (see "No final classes, anywhere"), an interface with exactly one implementation and no second caller has no reason to exist just to enable mocking, mock the concrete class's own leaf dependency instead: App\Actions\RunSnippetAction depends on App\Support\Herd directly, no wrapping interface, since Herd is mockable on its own. Before adding an interface "for testability", check whether the class it would wrap is even blocked from direct mocking.

## Use composer test as the final verification gate
Before considering a change done (committing, closing out a task/checkpoint), run `composer test` rather than manually chaining `pint`, `phpstan`, `pest --type-coverage`, and `pest --coverage` as separate commands — it already runs exactly that sequence (see composer.json's `test`/`test:*` scripts) in one shot and is less error-prone than reassembling it by hand each time.

This doesn't replace fast, targeted `php artisan test --compact --filter=X` runs while actively iterating on one piece (still the right tool for quick RED/GREEN feedback) — it's the gate before calling the work finished.
