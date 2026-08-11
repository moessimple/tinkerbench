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
