---
paths:
  - '**/*'
---

# General

## Consistency beats personal style
Consistency (naming, structure, testing style, mocking approach, route conventions) is the top-tier quality bar for this project, above cleverness or terseness. When a stylistic choice is ambiguous, check how the nearest comparable case was already solved in this codebase (or a cited reference project) before deciding, don't default to personal preference. Small deviations (a leading slash on a route, `test()` vs `it()`, a test name that leaks an internal collaborator name) are worth fixing, not "functionally identical, good enough".

## Single developer, single machine: don't harden against shared/multi-tenant risks
tinkerbench runs locally on one developer's own machine via Herd, not as a shared internal server or multi-tenant service. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. When reviewing or extending App\Support\Herd/snippet execution, don't propose defenses for risks that assume multiple concurrent users or other local OS accounts: output size caps to protect "other users" from one runaway snippet, temp file permission hardening against "other local accounts", rate limiting against multi-actor abuse, etc. A crash or resource exhaustion here affects only the developer running it and is self-recoverable with a restart. Reintroduce such defenses only if the deployment model changes to a shared/team instance.

## Full, isolated test coverage is mandatory, no silently invented exceptions
Every new/changed class in app/Actions|Support|Enums needs its own isolated unit test proving its behavior (see business-code.md); a new/changed Controller/Request/Middleware needs its own Http flow test instead (see tests.md). Every new/changed Vue component or JS module needs its own test too. This is mandatory, applies equally to PHP and JS/Vue, and includes plain enums, thin controllers, and anything else that looks "too small to test": don't invent an ad hoc exception (e.g. skipping a value-only enum's test) without flagging it to the user first and getting confirmation.

How to keep that coverage isolated and non-duplicated (mocking, one behavior per test, not re-proving a lower layer) is defined in tests.md for PHP and js.md for JS/Vue, follow those. When two sibling classes share a shape (e.g. two FormRequest-backed controllers), give them symmetric coverage.

## No final classes, anywhere
No class in the app is `final`. It blocks Mockery from creating a class double ("cannot override methods of a final class"), which forces awkward workarounds when a test needs to mock a class directly. Enforced project-wide by tests/ArchTest.php.

Readonly classes/properties are fine: they only cause the same Mockery problem (cannot extend a readonly class) on a class you actually need to mock directly in a test, e.g. an Action or a Support class with behavior. A plain DTO/value object that's never mocked, only constructed with real values, has no reason to avoid `readonly`.

## Don't keep single-implementation interfaces for mockability
Now that classes aren't final (see "No final classes, anywhere"), an interface with exactly one implementation and no second caller has no reason to exist just to enable mocking, mock the concrete class's own leaf dependency instead: App\Actions\RunSnippetAction depends on App\Support\Herd directly, no wrapping interface, since Herd is mockable on its own. Before adding an interface "for testability", check whether the class it would wrap is even blocked from direct mocking.
