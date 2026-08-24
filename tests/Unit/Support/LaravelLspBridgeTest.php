<?php

declare(strict_types=1);

use App\Support\LaravelLspBridge;
use Illuminate\Support\Facades\Process;

it('spawns a detached bridge process that survives past the request and reports its port', function (): void {
    $port = new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY);

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
    $port = new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY);

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

it('responds to a real initialize request through the bridged connection', function (): void {
    // laravel-lsp validates that its rootUri (rewritten from $projectPath by the bridge script,
    // regardless of what the client sends) is a real Laravel project, so this needs an actual
    // one - this project's own root, portable across machines and CI.
    $port = new LaravelLspBridge()->start(base_path(), PHP_BINARY);

    // Proves the spawned laravel-lsp process itself is reachable end-to-end, not just that the
    // wrapping bridge's own WebSocket server accepts a connection: a WS handshake succeeding
    // (the other tests here) says nothing about whether the child process behind it is alive,
    // since that process is only spawned once a client actually connects.
    $result = Process::run([
        config('services.herd.nvm_exec'),
        'node',
        '-e',
        "const ws = new (require('ws'))('wss://tinkerbench.test:{$port}', { rejectUnauthorized: false, headers: { Origin: 'https://tinkerbench.test' } }); ".
        "ws.on('open', () => ws.send(JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'initialize', params: { processId: null, rootUri: null, capabilities: {} } }))); ".
        "ws.on('message', (data) => { process.stdout.write(data.toString()); ws.close(); }); ".
        "ws.on('error', (error) => { process.stderr.write(String(error)); process.exitCode = 1; }); ".
        'setTimeout(() => { process.stderr.write("timed out"); process.exit(1); }, 15000);',
    ]);

    expect($result->output())->toContain('"serverInfo"');
});

it('throws when the herd Node runtime is not configured', function (): void {
    config(['services.herd.nvm_exec' => null]);

    new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY);
})->throws(InvalidArgumentException::class);

it('throws when the bridge script does not report a port', function (): void {
    new LaravelLspBridge()->start('', PHP_BINARY);
})->throws(InvalidArgumentException::class);
