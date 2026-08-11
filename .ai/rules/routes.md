---
paths:
  - 'routes/**'
---

# Routes

## No named routes, use Wayfinder action helpers
Do not chain `->name(...)` on routes. Frontend code references backend routes/controllers through Wayfinder's generated functions (`@/actions/...`, `@/routes/...`), not `route()`. Run `php artisan wayfinder:generate` after adding or changing a route/controller so the generated TS stays in sync.

## No named routes, use Wayfinder action helpers
Do not chain `->name(...)` on routes. Frontend code references backend routes/controllers through Wayfinder's generated functions (`@/actions/...`, `@/routes/...`), not `route()`. Run `php artisan wayfinder:generate` after adding or changing a route/controller so the generated TS stays in sync.

Avoid the `Route::inertia()` macro: Larastan can't resolve its return type (resolves to `mixed`), which forces a phpstan.neon ignore rule for any chained call. Use `Route::get($uri, fn () => Inertia::render($component))` instead, it's fully typed.
