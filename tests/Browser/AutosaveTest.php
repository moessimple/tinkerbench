<?php

declare(strict_types=1);

// OpenSnippet.vue debounces onEditorChange by 500 ms; the margin covers the PUT landing.
const AUTOSAVE_SETTLE = 0.7;

it('autosaves editor changes once the debounce settles', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, 'AUTOSAVE_DEBOUNCED_OK');

    $page->wait(AUTOSAVE_SETTLE)
        ->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertScript("document.querySelector('.monaco-editor .view-lines').innerText.includes('AUTOSAVE_DEBOUNCED_OK')")
        ->assertNoJavascriptErrors();
});

it('flushes the pending save on Cmd/Ctrl+S without waiting for the debounce', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, 'AUTOSAVE_FLUSHED_OK');

    $page->keys('.native-edit-context', ['ControlOrMeta+s'])
        ->waitForEvent('networkidle')
        ->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertScript("document.querySelector('.monaco-editor .view-lines').innerText.includes('AUTOSAVE_FLUSHED_OK')")
        ->assertNoJavascriptErrors();
});
