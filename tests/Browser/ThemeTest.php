<?php

declare(strict_types=1);

it('toggles the theme and keeps it across a navigation', function (): void {
    $page = visit('/')->inLightMode();
    stopAnimations($page);
    $page->assertVisible('.monaco-editor')
        ->assertScript("document.documentElement.classList.contains('dark')", false);

    $page->click('[aria-label="Switch to dark theme"]')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript("localStorage.getItem('theme') === 'dark'");

    $page->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript("localStorage.getItem('theme') === 'dark'")
        ->assertNoJavascriptErrors();
});
