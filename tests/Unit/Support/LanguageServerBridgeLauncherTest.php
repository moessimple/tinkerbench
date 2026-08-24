<?php

declare(strict_types=1);

use App\Support\LanguageServerBridgeLauncher;

it('spawns a detached process and reports the port it wrote to stdout', function (): void {
    $port = new LanguageServerBridgeLauncher()->start(
        base_path('app/Support/bin/intelephense-bridge.mjs'),
        [sys_get_temp_dir(), '8.5'],
    );

    expect($port)->toBeGreaterThan(0)->and($port)->toBeLessThanOrEqual(65535);
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
