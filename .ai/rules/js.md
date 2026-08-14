---
paths:
  - 'resources/js/**'
---

# Js

## Every exported function/component behavior gets its own isolated test
Coverage is measured per exported function or per observable component behavior (rendered output, emitted event, user interaction), not per file. Component.test.ts existing next to Component.vue only guarantees the component isn't forgotten, it doesn't guarantee every behavior is covered. Don't skip a behavior's test just because nothing currently exercises it, same rule as tests.md's "every public method gets its own isolated test", applied to JS/Vue.

## Mock only collaborators that already have their own test
Same rule as tests.md: once a child component, composable, or lib function has its own dedicated test file, `vi.mock()` it in callers' tests instead of re-proving its behavior (e.g. `OpenSnippet.test.ts` mocks `MonacoEditor.vue` and `CommandPalette.vue` because each has its own test). Note the reason in a comment next to the `vi.mock()` call, naming the sibling test file. Leaf-level pure functions/utilities with no collaborators of their own aren't mocked, they're exercised end to end. Mocking something purely because it doesn't work in the jsdom test environment (e.g. `@inertiajs/vue3`'s `Head`/`useHttp`) is a separate, environment-driven reason, not this rule, don't conflate the two in a comment.

## Match the PHP test conventions: it(), no describe(), one behavior per test
Vitest tests use `it('does X', ...)`, not `test('does X', ...)`, and no `describe()` wrapper, same reasoning as the PHP side: the file path already gives the grouping context. One behavior per test, don't combine "sends the right request" and "shows the result" in a single test. Vue component tests live co-located next to the component (Component.vue + Component.test.ts in the same folder), not mirrored under a separate tests/ tree, that's the idiomatic Vitest/Vue pattern and matches this project's own starter-kit scaffold (Welcome.vue/Welcome.test.ts).

DOM cleanup between tests is handled globally by vitest.setup.ts (registered via test.setupFiles in vitest.config.ts), so individual test files don't need afterEach(cleanup) boilerplate. Needed because @testing-library/vue only auto-registers cleanup when a real global afterEach exists, which isn't the case here since this project imports test utilities explicitly instead of using Vitest's globals.
