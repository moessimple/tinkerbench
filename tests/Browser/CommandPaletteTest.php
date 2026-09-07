<?php

declare(strict_types=1);

it('opens the palette over Monaco and offers to create a missing snippet', function (): void {
    $page = visitEditor();

    // Meta/Ctrl+P with Monaco focused: the capture-phase window listener must beat Monaco's
    // own keybinding service, open the dialog, and move focus into the search field.
    $page->click('.monaco-editor')
        ->keys('.native-edit-context', ['ControlOrMeta+p'])
        ->assertVisible('[role="dialog"]')
        ->assertScript("document.activeElement === document.querySelector('[role=\"combobox\"]')");

    // A name with no match offers creation.
    $page->typeSlowly('[role="combobox"]', 'nosuchsnippet', 15)
        ->assertSee('Press Enter to create it')
        ->assertNoJavaScriptErrors();
});
