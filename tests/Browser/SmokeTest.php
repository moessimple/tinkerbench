<?php

declare(strict_types=1);

it('renders the editor page', function (): void {
    $page = visit('/');
    stopAnimations($page);

    $page->assertSee('tinkerbench')
        ->assertSee('scratch')
        ->assertVisible('.monaco-editor')
        ->assertNoSmoke();
});

it('titles the tab with the project and snippet', function (): void {
    visit('/')
        ->assertTitleContains('tinkerbench')
        ->assertNoJavascriptErrors();
});
