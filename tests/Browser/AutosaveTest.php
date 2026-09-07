<?php

declare(strict_types=1);

// OpenSnippet.vue debounces the autosave 500 ms after the last keystroke. This waits well
// past that; the network-settle and the auto-retrying reload assertion below absorb the
// rest, so the reloaded value is never read before the save lands. This is the one
// deliberate fixed wait in the suite: once typing stops, nothing emits an event to wait
// for (.ai/rules/browser.md). The tests still settle on the rendered text before starting
// the wait, so the debounce is always scheduled before the clock does.
const AUTOSAVE_DEBOUNCE_SETTLE = 1.5;

// Counts content-save requests the page fires, snapshots that count at the instant
// Cmd/Ctrl+S is pressed, and records whether that keydown was default-prevented. Installed
// before typing. The page saves on a microtask (persistSnippet runs inside a promise
// callback), so __savesWhenCmdSPressed is read synchronously in the keydown listener before
// this keypress's own save can land: a value of 0 there means the 500 ms debounce had not
// fired on its own, which is what "before the debounce" has to prove without racing a clock.
const AUTOSAVE_SPY = <<<'JS'
    window.__contentSaves = 0;
    window.__savesWhenCmdSPressed = null;
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
            window.__savesWhenCmdSPressed = window.__contentSaves;
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

    // Every keystroke's change event has fired once the text is on screen, so the editor
    // content is complete before the keypress and no late change event can reschedule the
    // debounce after flushSave() clears it.
    $page->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK');

    $page->keys('.native-edit-context', ['ControlOrMeta+s'])
        ->assertScript('window.__savesWhenCmdSPressed === 0')
        ->assertScript('window.__cmdSDefaultPrevented === true')
        ->waitForEvent('networkidle')
        ->assertScript('window.__contentSaves === 1')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK')
        ->assertNoJavaScriptErrors();
});
