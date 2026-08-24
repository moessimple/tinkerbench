import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runBridge } from './language-server-bridge.mjs';

const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const LARAVEL_LSP_SCRIPT = path.join(PROJECT_ROOT, 'vendor/bin/laravel-lsp');

const [, , projectPath, phpBinary] = process.argv;

if (!projectPath || !phpBinary) {
    console.error('Usage: laravel-lsp-bridge.mjs <projectPath> <phpBinary>');
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
    spawnBin: phpBinary,
    spawnArgs: [LARAVEL_LSP_SCRIPT],
    rewriteMessage: rewriteToTargetProject,
});
