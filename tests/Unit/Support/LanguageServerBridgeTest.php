<?php

declare(strict_types=1);

use App\Support\LanguageServerBridge;
use App\Support\LanguageServerBridgeLauncher;
use Illuminate\Support\Facades\Process;

it('spawns a detached bridge process that survives past the request and reports its port', function (): void {
    $port = new LanguageServerBridge(new LanguageServerBridgeLauncher())->start(sys_get_temp_dir(), '8.5');

    expect($port)->toBeGreaterThan(0);

    $connected = Process::run([
        config('services.herd.nvm_exec'),
        'node',
        '-e',
        // rejectUnauthorized: false only skips certificate verification for this connectivity check, the
        // certificate chain itself is verified separately (it's tinkerbench.test's own Herd-issued certificate).
        "const ws = new (require('ws'))('wss://tinkerbench.test:{$port}', { rejectUnauthorized: false, headers: { Origin: 'https://tinkerbench.test' } }); ".
        "ws.on('open', () => { process.stdout.write('connected'); ws.close(); }); ".
        "ws.on('error', (error) => { process.stderr.write(String(error)); process.exitCode = 1; });",
    ]);

    expect($connected->output())->toContain('connected');
});

it('rejects a websocket handshake from another origin', function (): void {
    $port = new LanguageServerBridge(new LanguageServerBridgeLauncher())->start(sys_get_temp_dir(), '8.5');

    $connected = Process::run([
        config('services.herd.nvm_exec'),
        'node',
        '-e',
        "const ws = new (require('ws'))('wss://tinkerbench.test:{$port}', { rejectUnauthorized: false, headers: { Origin: 'https://evil.test' } }); ".
        "ws.on('open', () => { process.stdout.write('connected'); ws.close(); }); ".
        "ws.on('unexpected-response', () => { process.stdout.write('rejected'); });",
    ]);

    expect($connected->output())->toContain('rejected');
});
