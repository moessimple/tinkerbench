<?php

declare(strict_types=1);

use App\Support\LanguageServer\LanguageServerBridge;
use App\Support\LanguageServer\LanguageServerBridgeLauncher;
use Illuminate\Support\Facades\Process;

it('spawns a detached bridge process that survives past the request and reports its port', function (): void {
    $port = new LanguageServerBridge(new LanguageServerBridgeLauncher())->start(sys_get_temp_dir(), '8.5');

    expect($port)->toBeGreaterThan(0);

    $connected = Process::run([
        config('services.herd.nvm_exec'),
        'node',
        '-e',
        "const ws = new (require('ws'))('ws://tinkerbench.test:{$port}', { headers: { Origin: 'http://tinkerbench.test' } }); ".
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
        "const ws = new (require('ws'))('ws://tinkerbench.test:{$port}', { headers: { Origin: 'http://evil.test' } }); ".
        "ws.on('open', () => { process.stdout.write('connected'); ws.close(); }); ".
        "ws.on('unexpected-response', () => { process.stdout.write('rejected'); });",
    ]);

    expect($connected->output())->toContain('rejected');
});
