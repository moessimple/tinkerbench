---
paths:
  - 'src/**'
---

# Src

## Beyond CRUD layering: Domain, Application, Support
Business code lives in src/Application, src/Domain, src/Support, never in app/ (framework glue only). Domain holds business rules and knows nothing about HTTP or routing. Application holds HTTP-facing classes (controllers, requests, resources, middleware) and may depend on Domain, never the reverse. Support holds generic building blocks any layer may use.

## Namespace domain-first, not type-first
Business classes are namespaced by domain area first, then type: Domain\{Area}\Actions, not Domain\Actions\{Area}.

## Suffix classes by build type
Suffix by type: Action, Controller, Query, Request, Resource, Job, Middleware. Models and Enums are the exception, no suffix (plain domain noun, e.g. Post not PostModel).

## Every src class needs a 1:1 mirrored test
Each class in src/Domain, src/Application, src/Support gets a matching test at the same relative path under tests/ (src/Domain/Billing/Actions/CreateInvoiceAction.php mirrors tests/Domain/Billing/Actions/CreateInvoiceActionTest.php). Mandatory. tests/Architecture is the only exception, it checks cross-layer relationships instead of mirroring one class.
