import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runBridge } from './language-server-bridge.mjs';

const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const LARAVEL_LSP_BIN = path.join(PROJECT_ROOT, 'vendor/bin/laravel-lsp');

const [, , projectPath] = process.argv;

if (!projectPath) {
    console.error('Usage: laravel-lsp-bridge.mjs <projectPath>');
    process.exit(1);
}

function rewriteToTargetProject(message) {
    if (message.method === 'initialize' && message.params) {
        message.params.rootUri = `file://${projectPath}`;
        message.params.rootPath = projectPath;
        message.params.workspaceFolders = [{ uri: `file://${projectPath}`, name: 'project' }];
        // pestGenerateDocBlocks defaults to true and writes storage/framework/testing/_pest.php into the
        // target project on disk, a side effect this bridge shouldn't cause just by attaching.
        message.params.initializationOptions = { phpEnvironment: 'herd', pestGenerateDocBlocks: false };
    }

    return message;
}

runBridge({
    serverName: 'laravel-lsp',
    spawnBin: LARAVEL_LSP_BIN,
    spawnArgs: [],
    rewriteMessage: rewriteToTargetProject,
});
