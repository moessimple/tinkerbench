import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runBridge } from './language-server-bridge.mjs';

const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const INTELEPHENSE_BIN = path.join(PROJECT_ROOT, 'node_modules/.bin/intelephense');

const [, , projectPath, phpVersion] = process.argv;

if (!projectPath || !phpVersion) {
    console.error('Usage: intelephense-bridge.mjs <projectPath> <phpVersion>');
    process.exit(1);
}

// intelephense's PHP version target isn't an "initialize" param, it only takes effect through a
// workspace/didChangeConfiguration notification, so it has to be injected as a side effect once the
// client's own "initialized" notification passes through, rather than returned from the map itself.
function rewriteToTargetProject(message, serverConnection) {
    if (message.method === 'initialize' && message.params) {
        message.params.rootUri = `file://${projectPath}`;
        message.params.rootPath = projectPath;
        message.params.workspaceFolders = [{ uri: `file://${projectPath}`, name: 'project' }];
    }

    if (message.method === 'initialized') {
        serverConnection.writer.write({
            jsonrpc: '2.0',
            method: 'workspace/didChangeConfiguration',
            params: { settings: { intelephense: { environment: { phpVersion } } } },
        });
    }

    return message;
}

runBridge({
    serverName: 'intelephense',
    spawnBin: INTELEPHENSE_BIN,
    spawnArgs: ['--stdio'],
    rewriteMessage: rewriteToTargetProject,
});
