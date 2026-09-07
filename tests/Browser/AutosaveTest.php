<?php

declare(strict_types=1);

// OpenSnippet.vue debounces the autosave 500 ms after the last keystroke. This waits well
// past that; the network-settle and the auto-retrying reload assertion below absorb the
// rest, so the reloaded value is never read before the save lands. This is the one
// deliberate fixed wait in the suite: once typing stops, nothing emits an event to wait
// for (.ai/rules/browser.md).
const AUTOSAVE_DEBOUNCE_SETTLE = 1.5;

// Records every content-save request the page fires and whether the Cmd/Ctrl+S keydown was
// default-prevented. Installed before typing so the flush test can read it back with a
// single-shot assertScript() straight after the keypress, while the 500 ms debounce that
// would also eventually save is still pending.
const AUTOSAVE_SPY = <<<'JS'
    window.__contentSaves = 0;
    window.__cmdSDefaultPrevented = null;
    const nativeFetch = window.fetch;
    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : input.url;
        const method = (init && init.method ? init.method : 'GET').toUpperCase();
        if (method === 'PUT' && url.indexOf('/snippets/') !== -1) {
            window.__contentSaves++;
        }
        return nativeFetch.apply(window, arguments);
    };
    window.addEventListener('keydown', function (event) {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            window.__cmdSDefaultPrevented = event.defaultPrevented;
        }
    });
    JS;

it('autosaves editor changes once the debounce settles', function (): void {
    $page = visitEditor();

    typeIntoEditor($page, 'AUTOSAVE_DEBOUNCED_OK');

    $page->wait(AUTOSAVE_DEBOUNCE_SETTLE)
        ->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_DEBOUNCED_OK')
        ->assertNoJavaScriptErrors();
});

it('saves on Cmd/Ctrl+S before the debounce and suppresses the browser save dialog', function (): void {
    $page = visitEditor();
    $page->script(AUTOSAVE_SPY);

    typeIntoEditor($page, 'AUTOSAVE_FLUSHED_OK');

    // Cmd/Ctrl+S flushes http.code, which only holds the full text once every keystroke's
    // change event has landed. Settle on the rendered text first: a slow runner that applies
    // the last keystrokes after the keypress would otherwise flush a truncated save, then
    // queue the complete one on a 500 ms debounce that the hard navigate() below drops.
    $page->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK');

    // Guards the post-keypress check: it must read the pre-flush state, so the debounce
    // must still be pending here and no save may have gone out from typing alone.
    $page->assertScript('window.__contentSaves === 0');

    $page->keys('.native-edit-context', ['ControlOrMeta+s'])
        ->assertScript('window.__contentSaves === 1')
        ->assertScript('window.__cmdSDefaultPrevented === true');

    $page->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK')
        ->assertNoJavaScriptErrors();
});
