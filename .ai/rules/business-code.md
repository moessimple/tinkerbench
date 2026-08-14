---
paths:
  - 'src/**'
---

# Business Code

## Beyond CRUD layering: Domain, Application, Support
Business code lives in src/Application, src/Domain, src/Support, never in app/ (framework glue only). Domain holds business rules and knows nothing about HTTP or routing. Application holds HTTP-facing classes (controllers, requests, resources, middleware) and may depend on Domain, never the reverse. Support holds generic building blocks any layer may use.

## Namespace domain-first, not type-first
Business classes are namespaced by domain area first, then type: Domain\{Area}\Actions, not Domain\Actions\{Area}.

## Suffix classes by build type
Suffix by type: Action, Controller, Query, Request, Resource, Job, Middleware. Models and Enums are the exception, no suffix (plain domain noun, e.g. Post not PostModel).

## Every business code class needs a 1:1 mirrored test
Each class in src/Domain, src/Application, src/Support gets a matching test at the same relative path under tests/ (src/Domain/Billing/Actions/CreateInvoiceAction.php mirrors tests/Domain/Billing/Actions/CreateInvoiceActionTest.php). Mandatory. tests/Architecture is the only exception, it checks cross-layer relationships instead of mirroring one class.

The mirrored file only guarantees the class isn't forgotten, not that it's fully covered: tests.md's "every public method gets its own isolated test" is what actually closes that gap, follow it once the test file exists.

## No final classes, anywhere
No class in the app is `final`. It blocks Mockery from creating a class double ("cannot override methods of a final class"), which forces awkward workarounds when a test needs to mock a class directly. Decided after hitting this concretely with RunSnippetAction. Enforced project-wide by tests/Architecture/ArchTest.php, not just for src/Domain|Application|Support.

Readonly classes/properties are fine, including for Domain/Application/Support: they only cause the same Mockery problem (cannot extend a readonly class) on a class you actually need to mock directly in a test, e.g. an Action or a Support class with behavior. A plain DTO/value object that's never mocked, only constructed with real values, has no reason to avoid `readonly`.

## Don't keep single-implementation interfaces for mockability
Now that classes aren't final (see "No final classes, anywhere"), an interface with exactly one implementation and no second caller has no reason to exist just to enable mocking, mock the concrete class's own leaf dependency instead. Removed HerdContract for exactly this reason: RunSnippetAction's only real dependency was Herd itself, mocking Herd directly works fine once it's not final. Before adding an interface "for testability", check whether the class it would wrap is even blocked from direct mocking.
