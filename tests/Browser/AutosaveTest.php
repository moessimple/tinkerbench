<?php

declare(strict_types=1);

// OpenSnippet.vue debounces the autosave 500 ms after the last keystroke. This waits well
// past that; the network-settle and the auto-retrying reload assertion below absorb the
// rest, so the reloaded value is never read before the save lands. This is the one
// deliberate fixed wait in the suite: once typing stops, nothing emits an event to wait
// for (.ai/rules/browser.md). The test settles on the rendered text before starting the
// wait, so the debounce is always scheduled before the clock starts.
const AUTOSAVE_DEBOUNCE_SETTLE = 1.5;

// Counts content-save requests the page fires and records whether the Cmd/Ctrl+S keydown
// was default-prevented. Installed before typing. It also drops the 500 ms autosave
// debounce timer (OpenSnippet.vue schedules it with that exact delay), so once typing has
// settled the only code path left that can save is flushSave(), the Cmd/Ctrl+S handler
// under test. That turns "saved because of the keypress" into a plain equality check with
// no dependency on how fast the runner is.
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
    const nativeSetTimeout = window.setTimeout;
    window.setTimeout = function (handler, timeout) {
        if (timeout === 500) {
            return 0;
        }
        return nativeSetTimeout.apply(window, arguments);
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

    // The debounce is scheduled from the last change event, which on a loaded runner can
    // land after typeSlowly() returns. Settle on the rendered text so the fixed wait below
    // cannot elapse before the save is even scheduled; otherwise the hard navigate() drops
    // it, because a full page load never runs onBeforeUnmount's flush.
    $page->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_DEBOUNCED_OK');

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

    // The text on screen means every keystroke's change event has fired, so the editor
    // content is complete before the keypress.
    $page->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK');

    // The debounce is swallowed in AUTOSAVE_SPY, so a save here could only have come from
    // typing alone, which must not happen.
    $page->assertScript('window.__contentSaves === 0');

    $page->keys('.native-edit-context', ['ControlOrMeta+s'])
        ->assertScript('window.__cmdSDefaultPrevented === true')
        ->waitForEvent('networkidle')
        ->assertScript('window.__contentSaves === 1')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK')
        ->assertNoJavaScriptErrors();
});
