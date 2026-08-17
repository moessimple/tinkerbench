---
paths:
  - 'tests/Http/**'
---

# Tests Http Controllers

## Every controller test proves its own route middleware with toUseMiddleware()
Every Http controller test must include an `it('uses the right middleware', ...)` test asserting `expect(Controller::class)->toUseMiddleware(TheMiddleware::class)` for every middleware actually applied to that controller's route in routes/web.php (including EnsureKnownProject, not just controller-specific ones like HandlePrecognitiveRequests). Chain multiple `->toUseMiddleware(...)` calls in one test when a route has more than one. Controllers with no route middleware (e.g. ListProjectsController) need no such test. This mirrors how toUseFormRequest() proves request wiring, applied to middleware wiring instead.
