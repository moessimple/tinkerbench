import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runBridge } from './language-server-bridge.mjs';

const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const LARAVEL_LSP_SCRIPT = path.join(PROJECT_ROOT, 'vendor/bin/laravel-lsp');

const [, , projectPath, phpBinary, targetPhpBinary] = process.argv;

if (!projectPath || !phpBinary || !targetPhpBinary) {
    console.error('Usage: laravel-lsp-bridge.mjs <projectPath> <phpBinary> <targetPhpBinary>');
    process.exit(1);
}

function rewriteToTargetProject(message) {
    if (message.method === 'initialize' && message.params) {
        message.params.rootUri = `file://${projectPath}`;
        message.params.rootPath = projectPath;
        message.params.workspaceFolders = [{ uri: `file://${projectPath}`, name: 'project' }];
        message.params.initializationOptions = {
            // Bypasses laravel-lsp's own `herd which-php` auto-detection (phpEnvironment: 'herd'),
            // which fails under this app's nested spawn chain (PHP web request -> this bridge ->
            // php laravel-lsp): that inner `herd` invocation doesn't inherit a PATH with `herd` on
            // it either, silently falling back to an unqualified `php` that then can't run
            // `artisan tinker` against the target project at all. An already-resolved, explicit
            // binary sidesteps that resolution entirely.
            phpCommand: [targetPhpBinary],
            // pestGenerateDocBlocks defaults to true and writes storage/framework/testing/_pest.php into the
            // target project on disk, a side effect this bridge shouldn't cause just by attaching.
            pestGenerateDocBlocks: false,
        };
    }

    return message;
}

runBridge({
    serverName: 'laravel-lsp',
    spawnBin: phpBinary,
    spawnArgs: [LARAVEL_LSP_SCRIPT],
    rewriteMessage: rewriteToTargetProject,
});
