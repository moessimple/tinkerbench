---
paths:
  - 'tests/**'
---

# Tests

## Test level is free, behavior coverage is not
Unit vs feature/HTTP doesn't matter, cover the observable behavior end to end. Prefer unit tests for Domain classes; add feature/HTTP tests where routing, serialization, or multiple classes interacting needs proving.

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

## Test FormRequest validation via dispatchFormRequest(), not Validator::make()
To prove a FormRequest actually rejects/accepts a given input, use the dispatchFormRequest($requestClass, $payload) helper in tests/Pest.php (posts to a throwaway route bound to the request class, returns a real TestResponse to assert against with assertValid()/assertInvalid()). Do not call Validator::make($data, (new XRequest)->rules()) instead: that bypasses the FormRequest entirely, so it silently stops proving anything the moment the request gains authorize() or prepareForValidation() logic.

This coverage belongs in the FormRequest's own test file (e.g. UpdateSnippetNameRequestTest), not in the controller test. The controller test only needs toUseFormRequest() to prove wiring; re-asserting validation failure there duplicates what the Request's own test already owns.
