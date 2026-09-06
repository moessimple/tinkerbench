<?php

declare(strict_types=1);

// OpenSnippet.vue debounces onEditorChange by 500 ms. This waits out the debounce with a
// wide margin for a loaded runner; the network-settle and the auto-retrying reload
// assertion below absorb the rest, so the value is never read before the save lands.
const AUTOSAVE_DEBOUNCE_SETTLE = 1.5;

// Shorter than the 500 ms debounce: a change persisted after only this long can only have
// come from the Cmd/Ctrl+S flush, not the debounce timer.
const AUTOSAVE_FLUSH_SETTLE = 0.3;

it('autosaves editor changes once the debounce settles', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, 'AUTOSAVE_DEBOUNCED_OK');

    $page->wait(AUTOSAVE_DEBOUNCE_SETTLE)
        ->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_DEBOUNCED_OK')
        ->assertNoJavascriptErrors();
});

it('flushes the pending save on Cmd/Ctrl+S without waiting for the debounce', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, 'AUTOSAVE_FLUSHED_OK');
    $page->keys('.native-edit-context', ['ControlOrMeta+s']);

    $page->wait(AUTOSAVE_FLUSH_SETTLE)
        ->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertSeeIn('.monaco-editor .view-lines', 'AUTOSAVE_FLUSHED_OK')
        ->assertNoJavascriptErrors();
});
