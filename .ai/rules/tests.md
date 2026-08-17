---
paths:
  - 'tests/**'
---

# Tests

## Unit owns the behavior matrix, Http only proves the endpoint
tests/Http/ contains Controller tests only, flat, no Controllers/ subfolder, they prove a public endpoint: routing, controller-wiring (right FormRequest/Middleware/collaborator used, see controllers.md), and response shaping. Every other component, Actions, Support classes, Enums, FormRequests, Middleware, is not a public endpoint itself and belongs in tests/Unit/ instead, in a folder named after the component type (tests/Unit/Requests, tests/Unit/Middleware, tests/Unit/Support, tests/Unit/Actions, tests/Unit/Enums), not mirroring the full App\Http\ namespace depth. Its Unit test carries the component's full behavior matrix, even when proving it requires a real HTTP request/response cycle (a FormRequest's validation via createFormRequest(), a Middleware via a throwaway route), the mechanism used to invoke a component doesn't decide which folder its test lives in, only whether the component is itself a public endpoint does.

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
