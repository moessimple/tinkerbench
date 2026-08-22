<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRequestIsLocal;
use Illuminate\Support\Facades\Route;

it('lets a request from the loopback address through', function (): void {
    Route::get('api/request-under-test/path', fn (): string => 'ok')
        ->middleware(EnsureRequestIsLocal::class);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('api/request-under-test/path')
        ->assertOk()
        ->assertSee('ok');
});

it('rejects a request from a non-loopback address with a 403', function (): void {
    Route::get('api/request-under-test/path', fn (): string => 'ok')
        ->middleware(EnsureRequestIsLocal::class);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->getJson('api/request-under-test/path')
        ->assertForbidden()
        ->assertJsonPath('message', 'tinkerbench only accepts requests from this machine.');
});
