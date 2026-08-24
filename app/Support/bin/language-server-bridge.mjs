import { readFileSync } from 'node:fs';
import { createServer } from 'node:https';
import os from 'node:os';
import path from 'node:path';
import { toSocket } from 'vscode-ws-jsonrpc';
import { createServerProcess, createWebSocketConnection, forward } from 'vscode-ws-jsonrpc/server';
import { WebSocketServer } from 'ws';

// The parent PHP process (Process::options(['create_new_console' => true])) stops reading this
// process's own stdout/stderr once it has the reported port, and closes its read end of those
// pipes once its Process object is garbage collected shortly after. From that point on, this is
// a detached process with nothing left reading its stdio at all, so a write to either (e.g.
// createServerProcess()'s own `${serverName} Server: ...` stderr relay, further down) hits a
// closed pipe (EPIPE) and would otherwise crash the whole bridge as an uncaught exception.
process.stdout.on('error', () => {});
process.stderr.on('error', () => {});

const IDLE_TIMEOUT_MS = 5 * 60 * 1000;

// Safari (macOS 15+) blocks a plain ws:// connection from an https:// page as mixed content, even to
// 127.0.0.1, unlike Chrome/Firefox. Reusing tinkerbench.test's own Herd-issued certificate (signed by the
// already system-trusted Laravel Valet CA) lets the bridge speak wss:// without minting a new certificate.
// TINKERBENCH_HERD_CERTIFICATES_DIR overrides this for environments without Herd (CI), the same way
// services.herd.nvm_exec is already overridable.
const HERD_CERTIFICATES_DIR = process.env.TINKERBENCH_HERD_CERTIFICATES_DIR ?? path.join(
    os.homedir(),
    'Library/Application Support/Herd/config/valet/Certificates',
);

export function runBridge({ serverName, spawnBin, spawnArgs, rewriteMessage }) {
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

    httpsServer.once('listening', () => {
        process.stdout.write(`${httpsServer.address().port}\n`);
        resetIdleTimer();
    });

    httpsServer.listen(0, '127.0.0.1');

    server.on('connection', (socket) => {
        clearTimeout(idleTimer);

        socket.on('close', () => process.exit(0));
        socket.on('message', () => resetIdleTimer());

        const serverConnection = createServerProcess(serverName, spawnBin, spawnArgs);

        if (!serverConnection) {
            socket.close();

            return;
        }

        forward(createWebSocketConnection(toSocket(socket)), serverConnection, (message) =>
            rewriteMessage(message, serverConnection),
        );
    });
}
