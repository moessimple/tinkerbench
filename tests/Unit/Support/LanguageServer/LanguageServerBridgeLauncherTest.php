<?php

declare(strict_types=1);

use App\Support\LanguageServer\LanguageServerBridgeLauncher;

it('spawns a detached process and reports the port it wrote to stdout', function (): void {
    $port = new LanguageServerBridgeLauncher()->start(
        base_path('app/Support/bin/intelephense-bridge.mjs'),
        [sys_get_temp_dir(), '8.5'],
    );

    expect($port)->toBeGreaterThan(0)->and($port)->toBeLessThanOrEqual(65535);
});

it('does not leave a descriptor the caller had open still held by the detached bridge', function (): void {
    // proc_open() hands a spawned process every descriptor the caller has open, not just the
    // ones Process itself redirects. A socket pair stands in for that: it is open in this test
    // process exactly like, for example, a shell capturing this test run's own output would be.
    // If the bridge inherits it and survives (it is meant to, being detached), closing our own
    // end is not enough to reach EOF; the still-running bridge keeps its inherited copy open.
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    throw_unless($pair, RuntimeException::class, 'Could not create a Unix socket pair.');
    [$ours, $theirs] = $pair;

    new LanguageServerBridgeLauncher()->start(
        base_path('app/Support/bin/intelephense-bridge.mjs'),
        [sys_get_temp_dir(), '8.5'],
    );

    fclose($theirs);

    $read = [$ours];
    $write = [];
    $except = [];
    $sawEof = false;

    if (stream_select($read, $write, $except, 5) > 0) {
        fread($ours, 8192);
        $sawEof = feof($ours);
    }

    fclose($ours);

    expect($sawEof)->toBeTrue();
});

it('ignores output on other streams while waiting for the port line on stdout', function (): void {
    // '-e' as the "script path" runs this inline instead of a file, the same way `node -e` would
    // on a command line - the delay guarantees the stderr write is polled on its own before the
    // stdout write, rather than risking both arriving in the same poll.
    $port = new LanguageServerBridgeLauncher()->start('-e', [
        "process.stderr.write('a warning printed before the port is announced'); setTimeout(() => process.stdout.write('54213'), 100);",
    ]);

    expect($port)->toBe(54213);
});

it('throws when the herd Node runtime is not configured', function (): void {
    config(['services.herd.nvm_exec' => null]);

    new LanguageServerBridgeLauncher()->start(
        base_path('app/Support/bin/intelephense-bridge.mjs'),
        [sys_get_temp_dir(), '8.5'],
    );
})->throws(InvalidArgumentException::class);

it('throws when the script does not report a port', function (): void {
    new LanguageServerBridgeLauncher()->start('', []);
})->throws(InvalidArgumentException::class);
