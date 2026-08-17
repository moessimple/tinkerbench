import { readFileSync } from 'node:fs';
import { createServer } from 'node:https';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { toSocket } from 'vscode-ws-jsonrpc';
import { createServerProcess, createWebSocketConnection, forward } from 'vscode-ws-jsonrpc/server';
import { WebSocketServer } from 'ws';

const IDLE_TIMEOUT_MS = 5 * 60 * 1000;
const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const INTELEPHENSE_BIN = path.join(PROJECT_ROOT, 'node_modules/.bin/intelephense');

// Safari (macOS 15+) blocks a plain ws:// connection from an https:// page as mixed content, even to
// 127.0.0.1, unlike Chrome/Firefox. Reusing tinkerbench.test's own Herd-issued certificate (signed by the
// already system-trusted Laravel Valet CA) lets the bridge speak wss:// without minting a new certificate.
// TINKERBENCH_HERD_CERTIFICATES_DIR overrides this for environments without Herd (CI), the same way
// services.herd.nvm_exec is already overridable.
const HERD_CERTIFICATES_DIR = process.env.TINKERBENCH_HERD_CERTIFICATES_DIR ?? path.join(
    os.homedir(),
    'Library/Application Support/Herd/config/valet/Certificates',
);

const [, , projectPath, phpVersion] = process.argv;

if (!projectPath || !phpVersion) {
    console.error('Usage: intelephense-bridge.mjs <projectPath> <phpVersion>');
    process.exit(1);
}

const httpsServer = createServer({
    cert: readFileSync(path.join(HERD_CERTIFICATES_DIR, 'tinkerbench.test.crt')),
    key: readFileSync(path.join(HERD_CERTIFICATES_DIR, 'tinkerbench.test.key')),
});
// Rejects any WebSocket handshake not sent from tinkerbench's own page, so another origin open in the
// same browser can't drive this bridge's LSP session (cross-site WebSocket hijacking): unlike fetch(),
// browsers always send Origin on a WS handshake regardless of same-origin, so this is a reliable check.
const server = new WebSocketServer({
    server: httpsServer,
    verifyClient: ({ origin }) => origin === 'https://tinkerbench.test',
});

let idleTimer = null;

function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => process.exit(0), IDLE_TIMEOUT_MS);
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

httpsServer.once('listening', () => {
    process.stdout.write(`${httpsServer.address().port}\n`);
    resetIdleTimer();
});

httpsServer.listen(0, '127.0.0.1');

server.on('connection', (socket) => {
    clearTimeout(idleTimer);

    socket.on('close', () => process.exit(0));
    socket.on('message', () => resetIdleTimer());

    const serverConnection = createServerProcess('intelephense', INTELEPHENSE_BIN, ['--stdio']);

    if (!serverConnection) {
        socket.close();

        return;
    }

    forward(createWebSocketConnection(toSocket(socket)), serverConnection, (message) =>
        rewriteToTargetProject(message, serverConnection),
    );
});
