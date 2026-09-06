<?php

declare(strict_types=1);

it('keeps Cmd/Ctrl+F and F1 away from Monaco', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');
    $page->click('.monaco-editor');

    $page->keys('.native-edit-context', ['ControlOrMeta+f'])
        ->assertMissing('.find-widget');

    $page->keys('.native-edit-context', ['F1'])
        ->assertMissing('.quick-input-widget')
        ->assertNoJavascriptErrors();
});

it('still lets typing and the run chord through to Monaco', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, 'abc');
    $page->assertScript("document.querySelector('.monaco-editor .view-lines').innerText.includes('abc')");

    $page->keys('.native-edit-context', ['ControlOrMeta+Enter'])
        ->assertVisible('[role="tablist"][aria-label="Filter output by kind"]')
        ->assertNoJavascriptErrors();
});
