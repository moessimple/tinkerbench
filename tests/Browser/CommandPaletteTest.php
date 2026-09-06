<?php

declare(strict_types=1);

it('creates, renames and deletes a snippet from the palette over Monaco', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    // Meta/Ctrl+P with Monaco focused: the capture-phase window listener must beat Monaco's
    // own keybinding service and open the palette.
    $page->click('.monaco-editor')
        ->keys('.native-edit-context', ['ControlOrMeta+p'])
        ->assertVisible('[role="dialog"]');

    // A non-matching name offers creation; Enter creates it on the isolated disk and opens it.
    $page->typeSlowly('[role="combobox"]', 'browsercreated', 15)
        ->assertSee('Press Enter to create it')
        ->keys('[role="combobox"]', ['Enter'])
        ->assertPathIs('/tinkerbench/browsercreated');

    // Reopen, rename the row through the inline rename input. Select all then type rather
    // than fill(): fill()'s value commit has proved unreliable on CI Chromium, leaving the
    // field on its original name so the rename no-ops.
    $page->click('.monaco-editor')
        ->keys('.native-edit-context', ['ControlOrMeta+p'])
        ->assertVisible('[role="dialog"]')
        ->click('[aria-label="Rename browsercreated"]')
        ->keys('[aria-label="Rename browsercreated"]', ['ControlOrMeta+a'])
        ->typeSlowly('[aria-label="Rename browsercreated"]', 'browserrenamed', 15)
        ->keys('[aria-label="Rename browsercreated"]', ['Enter'])
        ->assertPathIs('/tinkerbench/browserrenamed');

    // Reopen, delete it; the palette drops back to the project root.
    $page->click('.monaco-editor')
        ->keys('.native-edit-context', ['ControlOrMeta+p'])
        ->assertVisible('[role="dialog"]')
        ->click('[aria-label="Delete browserrenamed"]')
        ->click('Yes')
        ->assertPathIs('/tinkerbench')
        ->assertDontSee('browserrenamed')
        ->assertNoJavascriptErrors();
});
