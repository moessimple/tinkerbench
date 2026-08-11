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

## assertMethodUsesType() for wiring-only proofs
tests/Pest.php defines assertMethodUsesType($class, $method, $type): a Reflection-based check that a method declares a parameter of the given type, no instantiation or mocking. Use it when a test only needs to prove a dependency is wired (e.g. a controller's __invoke() takes a specific Request/Action type), not that it's used correctly. Pair with a behavior-level test (real call or mock assertion) when the actual usage needs proving too; the reflection check alone only proves the type is declared.
