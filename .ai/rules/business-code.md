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

## No final, no readonly on classes
Domain/Application/Support classes are not `final` and not `readonly` (neither class-level nor per-property). Both block Mockery from creating a class double (final: "cannot override methods of a final class"; readonly: "non-readonly class cannot extend readonly class"), which forces awkward workarounds when a test needs to mock a class directly. Decided after hitting this concretely with RunSnippetAction. Rector's ReadOnlyPropertyRector and Pint's final_class/final_internal_class/final_public_method_for_abstract_class are disabled in rector.php/pint.json so neither gets re-added automatically.

## Don't keep single-implementation interfaces for mockability
Now that classes aren't final/readonly (see "No final, no readonly on classes"), an interface with exactly one implementation and no second caller has no reason to exist just to enable mocking, mock the concrete class's own leaf dependency instead. Removed HerdContract for exactly this reason: RunSnippetAction's only real dependency was Herd itself, mocking Herd directly works fine once it's not final. Before adding an interface "for testability", check whether the class it would wrap is even blocked from direct mocking.
