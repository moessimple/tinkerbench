<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Support\LanguageServerBridge;

it('spawns a detached bridge process that survives past the request and reports its port', function (): void {
    $port = new LanguageServerBridge()->start(sys_get_temp_dir(), '8.5');

    expect($port)->toBeGreaterThan(0);

    $connected = Process::run([
        config('services.tinkerbench.nvm_exec'),
        'node',
        '-e',
        // rejectUnauthorized: false only skips certificate verification for this connectivity check, the
        // certificate chain itself is verified separately (it's tinkerbench.test's own Herd-issued certificate).
        "const ws = new (require('ws'))('wss://tinkerbench.test:{$port}', { rejectUnauthorized: false }); ".
        "ws.on('open', () => { process.stdout.write('connected'); ws.close(); }); ".
        "ws.on('error', (error) => { process.stderr.write(String(error)); process.exitCode = 1; });",
    ]);

    expect($connected->output())->toContain('connected');
});

it('throws when the tinkerbench Node runtime is not configured', function (): void {
    config(['services.tinkerbench.nvm_exec' => null]);

    new LanguageServerBridge()->start(sys_get_temp_dir(), '8.5');
})->throws(InvalidArgumentException::class);

it('throws when the bridge script does not report a port', function (): void {
    new LanguageServerBridge()->start('', '');
})->throws(InvalidArgumentException::class);
