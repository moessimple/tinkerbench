---
paths:
  - 'app/Http/Controllers/**'
---

# Http Controllers

## Inject controller dependencies via __invoke(), not the constructor
Every controller here is a single-action invokable class (__invoke() only). A constructor property only earns its place if state is shared across multiple methods, which never happens here, so it's pure overhead. Inject dependencies (repositories, services) directly as __invoke() parameters instead, the same way route params and FormRequests already are. Laravel's container resolves them identically either way. Order: FormRequest first (if any), then service dependencies, then route params last (see RunSnippetController for the reference shape).

## JSON response contract: response()->json() directly, no Resources
Controllers return `response()->json()` directly, no `App\Http\Resources` classes. A mutation endpoint with nothing meaningful to return responds with `['ok' => true]`. A domain-level failure (not an infrastructure guard) responds with `['ok' => false, 'error' => '<message>']` and the matching HTTP status code (404/409), not an exception or `abort()`. Match this shape for new controllers.
