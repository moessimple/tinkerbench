import { cleanup } from '@testing-library/vue';
import { afterEach } from 'vitest';

afterEach(() => {
    cleanup();
});

// jsdom has no layout engine, so Element.prototype.scrollIntoView is undefined
// and any component that keeps an element in view (CommandPalette's keyboard
// navigation) throws when it calls it. A no-op stands in; tests that need to
// observe the call spy on this.
if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = () => {};
}
