<?php

declare(strict_types=1);

it('toggles the theme and keeps it across a navigation', function (): void {
    $page = visit('/')->inLightMode();
    stopAnimations($page);

    // The toggle's own label is the light/dark signal; asserting on it auto-waits, so the
    // synchronous classList/localStorage reads below never race the theme update.
    $page->assertVisible('.monaco-editor')
        ->assertVisible('[aria-label="Switch to dark theme"]')
        ->assertScript("document.documentElement.classList.contains('dark')", false);

    $page->click('[aria-label="Switch to dark theme"]')
        ->assertVisible('[aria-label="Switch to light theme"]')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript("localStorage.getItem('theme') === 'dark'");

    $page->navigate('/')
        ->assertVisible('.monaco-editor')
        ->assertVisible('[aria-label="Switch to light theme"]')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript("localStorage.getItem('theme') === 'dark'")
        ->assertNoJavascriptErrors();
});
