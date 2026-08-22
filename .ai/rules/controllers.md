---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Inject controller dependencies via __invoke(), not the constructor
Every controller here is a single-action invokable class (__invoke() only). Enforced by tests/Arch/HttpTest.php. A constructor property only earns its place if state is shared across multiple methods, which never happens here, so it's pure overhead. Inject dependencies (repositories, services) directly as __invoke() parameters instead, the same way route params and FormRequests already are. Laravel's container resolves them identically either way. Order: FormRequest first (if any), then service dependencies, then route params last (see RunSnippetController for the reference shape).

## JSON response contract: no Resources, failures go through abort()
Controllers return `response()->json()` directly, no `App\Http\Resources` classes. A mutation endpoint with nothing meaningful to return responds with `response()->noContent()` (204). A domain-level failure (missing/conflicting resource, same as an infrastructure guard like `EnsureKnownProject`) uses `abort()`/`abort_if()`/`abort_unless()` with the matching HTTP status code (404/409) and a message, not a hand-built `response()->json(['ok' => false, ...])`. This keeps the failure shape (`{"message": "..."}`) consistent with Laravel's own validation-error responses, and avoids a redundant `ok` flag the client never reads (`response.ok` from the HTTP status already tells it that). Because `APP_DEBUG=true` locally adds `exception`/`file`/`line`/`trace` to the body, tests assert `assertJsonPath('message', '<text>')`, not `assertExactJson()`. Match this shape for new controllers.

## Use Response::HTTP_* constants in abort()/abort_if()/abort_unless(), not bare status codes
Pass `Response::HTTP_NOT_FOUND`, `Response::HTTP_CONFLICT`, `Response::HTTP_INTERNAL_SERVER_ERROR`, etc. as the status code argument, not a bare int like `404`/`409`/`500`. Every controller here already imports `Illuminate\Http\Response` for its own `__invoke()` return type, and that class inherits all `HTTP_*` constants from `Symfony\Component\HttpFoundation\Response`, so no extra import is needed. For a controller whose own return type isn't `Illuminate\Http\Response` (e.g. `OpenSnippetController` returns `Inertia\Response`), import `Symfony\Component\HttpFoundation\Response as HttpResponse` instead and use `HttpResponse::HTTP_*`.
