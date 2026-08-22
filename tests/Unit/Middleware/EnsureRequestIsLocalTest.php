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

it('rejects a request tunneled in via herd share, whose real visitor is reported through X-Forwarded-For', function (): void {
    Route::get('api/request-under-test/path', fn (): string => 'ok')
        ->middleware(EnsureRequestIsLocal::class);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
        ->getJson('api/request-under-test/path')
        ->assertForbidden();
});

it('ignores a spoofed X-Forwarded-For from a peer that is not the trusted local proxy', function (): void {
    Route::get('api/request-under-test/path', fn (): string => 'ok')
        ->middleware(EnsureRequestIsLocal::class);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
        ->withHeaders(['X-Forwarded-For' => '127.0.0.1'])
        ->getJson('api/request-under-test/path')
        ->assertForbidden();
});
