---
paths:
  - 'tests/**'
  - tests/ArchTest.php
---

# Tests

## Unit owns the behavior matrix, Http only proves the endpoint
tests/Http/ contains Controller tests only, flat, no Controllers/ subfolder, they prove a public endpoint: routing, controller-wiring (right FormRequest/Middleware/collaborator used, see below), and response shaping. Every other component, Actions, Support classes, Enums, FormRequests, Middleware, is not a public endpoint itself and belongs in tests/Unit/ instead, in a folder named after the component type (tests/Unit/Requests, tests/Unit/Middleware, tests/Unit/Support, tests/Unit/Actions, tests/Unit/Enums), not mirroring the full App\Http\ namespace depth. Its Unit test carries the component's full behavior matrix, even when proving it requires a real HTTP request/response cycle (a FormRequest's validation via createFormRequest(), a Middleware via a throwaway route), the mechanism used to invoke a component doesn't decide which folder its test lives in, only whether the component is itself a public endpoint does.

## Every controller test proves its own route middleware with toUseMiddleware()
Every Http controller test must include an `it('uses the right middleware', ...)` test asserting `expect(Controller::class)->toUseMiddleware(TheMiddleware::class)` for every middleware actually applied to that controller's route in routes/web.php (including EnsureKnownProject, not just controller-specific ones like HandlePrecognitiveRequests). Chain multiple `->toUseMiddleware(...)` calls in one test when a route has more than one. Controllers with no route middleware (e.g. ListProjectsController) need no such test. This mirrors how toUseFormRequest() proves request wiring, applied to middleware wiring instead.

## Every controller test proves its own FormRequest with toUseFormRequest()
Every Http controller test whose controller declares a FormRequest parameter (not the base Illuminate\Http\Request) must include an `it('uses the right request', ...)` test asserting `expect(Controller::class)->toUseFormRequest(TheRequest::class)`. Mirrors the middleware rule above, `toUseFormRequest()` is required whenever the controller takes a FormRequest. Controllers with no FormRequest (e.g. ListProjectsController, DeleteSnippetController, StartLanguageServerController) need no such test.

## Mock SnippetRepository in every controller test that depends on it
Every controller test whose controller depends on App\Support\SnippetRepository mocks it with $this->mock() (single expectation) or $this->mock(SnippetRepository::class, function (MockInterface $mock) {...}) when a controller calls more than one repository method (e.g. OpenSnippetController's ensureExists()+contents()). None use Storage::fake('snippets'); that belongs only in SnippetRepositoryTest itself. Controllers that don't depend on SnippetRepository at all (e.g. RunSnippetController, which depends on RunSnippetAction instead, or any Projects controller) mock whatever they do depend on, following the same "mock only collaborators with their own test" rule.

Why: SnippetRepository already has full, dedicated test coverage (SnippetRepositoryTest), so a controller test only needs to prove its own responsibility, delegating with the right arguments and shaping the response/Inertia props correctly from whatever the repository returns. Re-touching real storage in every controller test re-proved behavior SnippetRepositoryTest already owns (e.g. "creates missing snippet with default content" is ensureExists()'s own behavior, not the controller's).

How to apply: when adding a new Snippets controller that depends on SnippetRepository, mock it the same way; assert only the controller's own branching (status codes, response shape) driven by the mocked return value, not storage side effects.

## Split delegation wiring from response shaping in controller tests
Once a controller's collaborator (Support class, Action, FormRequest, Middleware) already has its own complete test elsewhere, don't re-prove its behavior here, only prove the controller's own responsibility: that it delegates correctly, and that it shapes the response correctly from whatever the collaborator returns.

Split these into separate tests, don't blend them:
- One dedicated wiring test per collaborator: mock with strict `->once()->with(...)`, assert nothing about the response. This is the delegation proof.
- One test per branch/outcome: mock loosely (`->andReturn(...)` only, no `once()`/`with()`), assert the resulting status code and JSON body. This is the response-shaping proof.

Reference examples: RunSnippetControllerTest.php (uses the right action) and UpdateSnippetNameControllerTest.php (uses the right repository) vs. their respective response-branch tests. Skip the split only when there is no separate collaborator to mock (e.g. pure routing logic embedded directly in the controller); in that case the controller test is the only place proving that behavior and should cover it fully.

## Skip re-testing a decision made entirely outside the controller
The line isn't "wiring vs. response", it's who makes the decision. Always test a response branch the controller itself builds from a collaborator's result (e.g. UpdateSnippetNameController mapping RenameSnippetResult to 200/404/409), that's the controller's own job and its test is the only place proving it.

Skip re-testing a branch that something else decides entirely before the controller ever runs, when that something already has both a wiring proof pointing at this exact route (toUseMiddleware(), toUseFormRequest()) and its own dedicated test proving the behavior. Example: EnsureKnownProject aborts unknown-project requests with a 404 before ListSnippetsController/StartLanguageServerController even execute; that path is already proven by EnsureKnownProjectTest, re-testing "rejects a project unknown to herd" in the controller test would just be a second copy of that same proof, not a test of the controller's own response shaping.

## Assert behavior, not implementation details
Assert return values, persisted state, dispatched events/jobs, HTTP responses. Not private methods or internal call order, unless the delegation itself is a documented contract.

## Mock only collaborators that already have their own test
Once a class has its own complete test, fake/mock it in callers' tests instead of re-proving its behavior. A caller's test proves only its own responsibility (delegation, wiring, its own transformation).

## One behavior per test
A test proving a mocked collaborator was used and a test proving the actual response are two different claims. Don't blend them into one test whose assertion only covers half of what its name promises.

## Name tests with plain words, not pattern jargon
Prefer "uses" over "delegates", generally the plainest accurate verb over pattern-language terms (delegation, orchestration, composition, ...).

## Every public method gets its own isolated test
Test coverage is measured per public method, not per "is this method called by something today". If a class/interface declares a public method, it gets its own test proving that method's behavior in isolation, independent of whether a current caller happens to exercise it. Do not skip a public method's test just because nothing in the codebase calls it yet.

## Use it(), not test()
Write tests with Pest's `it('does X', ...)`, not `test('does X', ...)`. Reads more naturally ("it does X"). Applies to every test in the suite.

## toUseType()/toUseFormRequest() for wiring-only proofs
tests/Pest.php defines custom expectations `expect($class)->toUseType($type)` and `expect($class)->toUseFormRequest($type)`: Reflection-based checks that a class's __invoke() declares a parameter of the given type, no instantiation or mocking. `toUseFormRequest()` additionally asserts the type is actually a subclass of FormRequest. Use these when a test only needs to prove a dependency is wired, not that it's used correctly. Pair with a behavior-level test (real call or mock assertion) when the actual usage needs proving too; the reflection check alone only proves the type is declared.

## Test FormRequest validation via createFormRequest(), not Validator::make()
A plain `expect($request->rules())->toBe([...])` unit check only proves the declared rule array, not that it actually behaves as intended, that's normally enough on its own. Reach for the `createFormRequest($requestClass, $payload)` helper in tests/Pest.php only when that unit check isn't sufficient to secure a specific edge case (a regex/format rule, conditional/cross-field rules, a custom rule object, authorize()/prepareForValidation() logic, route-parameter-aware rules, ...). It posts to a throwaway route bound to the request class and returns a real TestResponse to assert against with assertValid()/assertInvalid(), so the request is actually resolved and validated end to end. Do not use Validator::make($data, (new XRequest)->rules()) for this: that bypasses the FormRequest entirely, so it silently stops proving anything the moment the request gains authorize() or prepareForValidation() logic.

This coverage belongs in the FormRequest's own test file (e.g. UpdateSnippetNameRequestTest), not in the controller test. The controller test only needs toUseFormRequest() to prove wiring; re-asserting validation failure there duplicates what the Request's own test already owns.

## Load-bearing for `pest --parallel`

`test:unit` runs `pest --parallel`. Two things keep the arch preset tests green under it; each is commented at its site, do not remove them:

- `tests/Pest.php` resets the `App\` PSR-4 map to `app/` only. laravel/pint and laravel/lsp (Laravel Zero CLI tools) also register `App\` into the shared autoloader, incl. a colliding `App\Providers\AppServiceProvider`; a `--parallel` worker resolves it to the path-less vendor copy and pest-plugin-arch crashes with `$path must not be accessed before initialization`. Nothing loads those vendor classes (both tools run as subprocesses).
- `arch()->preset()->php()->ignoring('debug_backtrace')` in `tests/ArchTest.php`. `SourceLocator::snippetLine()` uses it as the line-attribution mechanism, not as a debug leftover; the preset only misflags it under `--parallel`.

## Tests\TestCase disables Inertia SSR
`config('inertia.ssr.enabled')` is `true`. When `npm run dev` is running, inertia-laravel dispatches SSR to the Vite dev endpoint (`{APP_URL}:5173/__inertia_ssr`); in a test that is a stray HTTP request that 500s every `assertInertia()` in `tests/Http/OpenSnippetControllerTest`. `Tests\TestCase::setUp()` sets `config(['inertia.ssr.enabled' => false])` so the suite passes whether or not the dev server is up. `withoutVite()` alone does NOT prevent this (inertia-laravel still finds the running dev server). Do not drop the config override.
