<?php

declare(strict_types=1);

use App\Support\LaravelLspBridge;
use Illuminate\Support\Facades\Process;

it('spawns a detached bridge process that survives past the request and reports its port', function (): void {
    $port = new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY, PHP_BINARY);

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
    $port = new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY, PHP_BINARY);

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
    $port = new LaravelLspBridge()->start(base_path(), PHP_BINARY, PHP_BINARY);

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

it('resolves real config completions for the target project, not just a stub response', function (): void {
    // Regression test: laravel-lsp's own `phpEnvironment: herd` auto-detection (`herd which-php`)
    // fails silently under this app's own nested spawn chain (PHP web request -> bridge -> php
    // laravel-lsp), falling back to an unqualified `php` that isn't on that chain's PATH either -
    // laravel-lsp then can't run `artisan tinker` against the target project at all, and every
    // completion request returns a PHP-runner error instead of real results. Passing an explicit,
    // already-resolved $targetPhpBinary as `phpCommand` sidesteps that broken auto-detection
    // entirely. A bare "did the socket open" test can't catch this - it needs a real completion
    // round trip against a real project.
    $port = new LaravelLspBridge()->start(base_path(), PHP_BINARY, PHP_BINARY);

    $result = Process::run([
        config('services.herd.nvm_exec'),
        'node',
        '-e',
        "const ws = new (require('ws'))('wss://tinkerbench.test:{$port}', { rejectUnauthorized: false, headers: { Origin: 'https://tinkerbench.test' } }); ".
        'let id = 1; const pending = new Map(); '.
        "function send(m) { ws.send(JSON.stringify({ jsonrpc: '2.0', ...m })); } ".
        'function request(method, params) { const rid = id++; return new Promise((r) => { pending.set(rid, r); send({ id: rid, method, params }); }); } '.
        'function notify(method, params) { send({ method, params }); } '.
        "ws.on('message', (data) => { const m = JSON.parse(data.toString()); if (m.id !== undefined && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } }); ".
        "ws.on('open', async () => { ".
        '  await request("initialize", { processId: null, rootUri: null, capabilities: {} }); '.
        '  notify("initialized", {}); '.
        "  const uri = 'file:///tinkerbench-snippet.php'; ".
        '  notify("textDocument/didOpen", { textDocument: { uri, languageId: "php", version: 1, text: "<?php\\n\\necho config(\'" } }); '.
        '  await new Promise((r) => setTimeout(r, 3000)); '.
        '  const completion = await request("textDocument/completion", { textDocument: { uri }, position: { line: 2, character: 13 } }); '.
        '  const items = Array.isArray(completion.result) ? completion.result : (completion.result?.items ?? []); '.
        '  process.stdout.write(JSON.stringify({ count: items.length, error: completion.error?.message ?? null })); '.
        '  process.exit(0); '.
        '}); '.
        "ws.on('error', (error) => { process.stderr.write(String(error)); process.exitCode = 1; }); ".
        'setTimeout(() => { process.stderr.write("timed out"); process.exit(1); }, 15000);',
    ]);

    $decoded = json_decode($result->output(), true);

    expect($decoded)->not->toBeNull()->and($decoded['error'])->toBeNull()->and($decoded['count'])->toBeGreaterThan(0);
});

it('throws when the herd Node runtime is not configured', function (): void {
    config(['services.herd.nvm_exec' => null]);

    new LaravelLspBridge()->start(sys_get_temp_dir(), PHP_BINARY, PHP_BINARY);
})->throws(InvalidArgumentException::class);

it('throws when the bridge script does not report a port', function (): void {
    new LaravelLspBridge()->start('', PHP_BINARY, PHP_BINARY);
})->throws(InvalidArgumentException::class);
