---
paths:
  - 'resources/js/**'
---

# Js

## Match the PHP test conventions: it(), no describe(), one behavior per test
Vitest tests use `it('does X', ...)`, not `test('does X', ...)`, and no `describe()` wrapper, same reasoning as the PHP side: the file path already gives the grouping context. One behavior per test, don't combine "sends the right request" and "shows the result" in a single test. Vue component tests live co-located next to the component (Component.vue + Component.test.ts in the same folder), not mirrored under a separate tests/ tree, that's the idiomatic Vitest/Vue pattern and matches this project's own starter-kit scaffold (Welcome.vue/Welcome.test.ts).

DOM cleanup between tests is handled globally by vitest.setup.ts (registered via test.setupFiles in vitest.config.ts), so individual test files don't need afterEach(cleanup) boilerplate. Needed because @testing-library/vue only auto-registers cleanup when a real global afterEach exists, which isn't the case here since this project imports test utilities explicitly instead of using Vitest's globals.
