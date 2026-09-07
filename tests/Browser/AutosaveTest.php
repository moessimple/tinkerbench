<?php

declare(strict_types=1);

// Spies on the autosave requests OpenSnippet.vue fires. __contentSaves counts PUTs issued;
// __savedOk maps a saved content string to true once its PUT has resolved 2xx;
// __cmdSDefaultPrevented records whether the Cmd/Ctrl+S keydown was default-prevented.
// assertScript() is retried up to the suite timeout, so a test waits for a save to actually
// reach the server by asserting on __savedOk before it navigates: a hard navigate() aborts
// an in-flight request, and waitForLoadState('networkidle') does not cover a later fetch.
const AUTOSAVE_SPY = <<<'JAVASCRIPT'
    window.__contentSaves = 0;
    window.__cmdSDefaultPrevented = null;
    window.__savedOk = {};
    const nativeFetch = window.fetch;
    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : input.url;
        const method = (init && init.method ? init.method : 'GET').toUpperCase();
        if (method === 'PUT' && url.indexOf('/snippets/') !== -1) {
            window.__contentSaves++;
            let saved = null;
            try {
                saved = JSON.parse(init.body).content;
            } catch (error) {
                saved = null;
            }
            return nativeFetch.apply(window, arguments).then(function (response) {
                if (response.ok && saved !== null) {
                    window.__savedOk[saved] = true;
                }
                return response;
            });
        }
        return nativeFetch.apply(window, arguments);
    };
    window.addEventListener('keydown', function (event) {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            window.__cmdSDefaultPrevented = event.defaultPrevented;
        }
    });
    JAVASCRIPT;

// Swallows OpenSnippet.vue's 500 ms autosave debounce (its only window.setTimeout with that
// exact delay). The Cmd/Ctrl+S test installs this so flushSave() is the sole path left that
// can save, which makes "the keypress saved" a plain equality instead of a clock race.
const DISABLE_AUTOSAVE_DEBOUNCE = <<<'JAVASCRIPT'
    const nativeSetTimeout = window.setTimeout;
    window.setTimeout = function (handler, timeout) {
        return timeout === 500 ? 0 : nativeSetTimeout.apply(window, arguments);
    };
    JAVASCRIPT;

it('autosaves editor changes once the debounce settles', function (): void {
    $page = visitEditor();
    $page->script(AUTOSAVE_SPY);

    typeIntoEditor($page, 'AUTOSAVE_DEBOUNCED_OK');

    // assertScript() retries up to the suite timeout, so this waits out the 500 ms debounce
    // and the request round-trip with no fixed wait, and only proceeds once the full text
    // has been saved 2xx. Reloading before that would abort the in-flight save.
    $page->assertScript('window.__savedOk["AUTOSAVE_DEBOUNCED_OK"] === true')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_DEBOUNCED_OK')
        ->assertNoJavaScriptErrors();
});

it('saves on Cmd/Ctrl+S before the debounce and suppresses the browser save dialog', function (): void {
    $page = visitEditor();
    $page->script(AUTOSAVE_SPY);
    $page->script(DISABLE_AUTOSAVE_DEBOUNCE);

    typeIntoEditor($page, 'AUTOSAVE_FLUSHED_OK');

    // The text on screen means every keystroke's change event has fired, so the editor
    // content is complete before the keypress.
    $page->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK');

    // The debounce is disabled, so a save here could only have come from typing alone.
    $page->assertScript('window.__contentSaves === 0');

    $page->keys('.native-edit-context', ['ControlOrMeta+s'])
        ->assertScript('window.__cmdSDefaultPrevented === true')
        ->assertScript('window.__contentSaves === 1')
        ->assertScript('window.__savedOk["AUTOSAVE_FLUSHED_OK"] === true')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK')
        ->assertNoJavaScriptErrors();
});
