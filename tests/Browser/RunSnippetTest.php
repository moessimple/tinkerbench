<?php

declare(strict_types=1);

it('runs a snippet and shows its return value and the queries facet', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    // DB resolves through Laravel's global facade alias inside the runner, so the snippet
    // needs no import; select('select 1') captures one query without touching a table.
    typeIntoEditor($page, "<?php DB::select('select 1'); return 1 + 1;");
    $page->keys('.native-edit-context', ['ControlOrMeta+Enter']);

    $page->assertSeeIn('article[data-label="Result"]', '2')
        ->assertSeeIn('[role="tablist"][aria-label="Filter output by kind"]', 'Queries')
        ->assertVisible('article[data-label="Query"]')
        ->assertNoJavascriptErrors();
});

it('re-runs the VarDumper script so a dump renders', function (): void {
    $page = visit('/');
    stopAnimations($page);
    $page->assertVisible('.monaco-editor');

    typeIntoEditor($page, "<?php dump(['a' => 1]);");
    $page->keys('.native-edit-context', ['ControlOrMeta+Enter']);

    // A visible .sf-dump proves executeScripts() re-ran VarDumper's inline <script>; no JS
    // errors proves the isolate/z-index dump-pane fix still holds.
    $page->assertVisible('.sf-dump')
        ->assertNoJavascriptErrors();
});
