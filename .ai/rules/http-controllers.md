---
paths:
  - 'app/Http/Controllers/**'
---

# Http Controllers

## Inject controller dependencies via __invoke(), not the constructor
Every controller here is a single-action invokable class (__invoke() only). A constructor property only earns its place if state is shared across multiple methods, which never happens here, so it's pure overhead. Inject dependencies (repositories, services) directly as __invoke() parameters instead, the same way route params and FormRequests already are. Laravel's container resolves them identically either way. Order: FormRequest first (if any), then service dependencies, then route params last (see RunSnippetController for the reference shape).

## JSON response contract: no Resources, failures go through abort()
Controllers return `response()->json()` directly, no `App\Http\Resources` classes. A mutation endpoint with nothing meaningful to return responds with `response()->noContent()` (204). A domain-level failure (missing/conflicting resource, same as an infrastructure guard like `EnsureKnownProject`) uses `abort()`/`abort_if()`/`abort_unless()` with the matching HTTP status code (404/409) and a message, not a hand-built `response()->json(['ok' => false, ...])`. This keeps the failure shape (`{"message": "..."}`) consistent with Laravel's own validation-error responses, and avoids a redundant `ok` flag the client never reads (`response.ok` from the HTTP status already tells it that). Because `APP_DEBUG=true` locally adds `exception`/`file`/`line`/`trace` to the body, tests assert `assertJsonPath('message', '<text>')`, not `assertExactJson()`. Match this shape for new controllers.
