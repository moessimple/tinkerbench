---
paths:
  - 'app/Actions/**'
---

# Actions

Actions hold business logic extracted out of the entry point that triggers it (controller, job, command, etc.) so that logic isn't embedded directly in the entry point itself. A single caller today doesn't disqualify the extraction, keeping the entry point thin and giving the logic its own isolated unit test are reasons enough on their own. Naming/suffix and the mandatory 1:1 unit test mirror are shared with Support/Enums, see app.md.

## Single execute() entrypoint
Actions expose exactly one public method, `execute()`, not `handle()`. Enforced by tests/Arch/ActionsTest.php: only `__construct` and `execute` may be public.

## Constructor-inject dependencies as private properties
Use constructor property promotion (see App\Actions\RunSnippetAction). Skip the constructor entirely when the action needs no dependencies.

## No final, no readonly
Actions are mocked directly in controller tests (`$this->mock(SomeAction::class)`); both `final` and `readonly` block Mockery from creating a class double (see general.md "No final classes, anywhere").

## make:action generates the wrong shape, fix it after
`php artisan make:action --no-interaction` scaffolds via the nunomaduro/essentials stub, which defaults to `final readonly class` and a `handle()` method. Rename `handle` to `execute` and drop `final`/`readonly` after generating.

## Wrap multi-model writes in a transaction
When an action writes to more than one model, wrap the writes in `DB::transaction()` inside the action itself, not at the call site.

## session()/auth()/request() stay in App\Http
Actions and Support classes must not call session(), auth(), or request() directly, that pulls implicit HTTP-request context into code that's supposed to be callable from anywhere (a command, a job, a test), not only from behind a web request. Resolve what's needed in the controller/request and pass it in explicitly. Enforced by tests/Arch/GlobalsTest.php.
