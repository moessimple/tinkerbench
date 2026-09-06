<?php

declare(strict_types=1);

it('keeps Cmd/Ctrl+F and F1 away from Monaco', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');
    $page->click('.monaco-editor');

    // The widget never opens (assertMissing), and focus stays in the editor rather than
    // moving into a find/palette input (the synchronous activeElement check). Both together
    // rule out a false pass where the widget simply had not appeared yet.
    $page->keys('.native-edit-context', ['ControlOrMeta+f'])
        ->assertMissing('.find-widget')
        ->assertScript("document.activeElement.classList.contains('native-edit-context')");

    $page->keys('.native-edit-context', ['F1'])
        ->assertMissing('.quick-input-widget')
        ->assertScript("document.activeElement.classList.contains('native-edit-context')")
        ->assertNoJavascriptErrors();
});

it('still lets typing and the run chord through to Monaco', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    // Valid PHP so the run produces a clean output card: the visible `echo 42` proves
    // typing reached the model, and `42` in the output proves Ctrl/Cmd+Enter ran it
    // instead of being swallowed by Monaco.
    typeIntoEditor($page, '<?php echo 42;');
    $page->assertSeeIn('.monaco-editor .view-lines', 'echo 42');

    $page->keys('.native-edit-context', ['ControlOrMeta+Enter'])
        ->assertSeeIn('article[data-label="Output"]', '42')
        ->assertNoJavascriptErrors();
});
