<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // config('inertia.ssr.enabled') is true, and inertia-laravel dispatches SSR to the Vite
        // dev endpoint whenever `npm run dev` is running. In a test that is a stray HTTP request
        // that 500s every assertInertia() in OpenSnippetControllerTest. Turn SSR off so the suite
        // passes whether or not the dev server is up. withoutVite() alone does not stop this.
        config(['inertia.ssr.enabled' => false]);
    }
}
