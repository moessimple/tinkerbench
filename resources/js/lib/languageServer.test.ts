import type * as Monaco from 'monaco-editor';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { attachLanguageServer } from '@/lib/languageServer';

const sockets: FakeWebSocket[] = [];

class FakeWebSocket {
    static OPEN = 1;

    readyState = 0;
    sent: string[] = [];
    readonly url: string;
    private readonly listeners: Record<
        string,
        ((event: { data?: string }) => void)[]
    > = {};

    constructor(url: string) {
        this.url = url;
        sockets.push(this);
    }

    addEventListener(
        type: string,
        callback: (event: { data?: string }) => void,
    ): void {
        (this.listeners[type] ??= []).push(callback);
    }

    send(data: string): void {
        this.sent.push(data);
    }

    close(): void {
        this.readyState = 3;
    }

    open(): void {
        this.readyState = FakeWebSocket.OPEN;
        this.listeners.open?.forEach((callback) => callback({}));
    }

    receive(message: unknown): void {
        this.listeners.message?.forEach((callback) =>
            callback({ data: JSON.stringify(message) }),
        );
    }
}

const monaco = {
    languages: {
        CompletionItemKind: {
            Text: 18,
            Method: 0,
            Function: 1,
            Field: 3,
            Variable: 4,
            Class: 5,
            Module: 8,
            Property: 9,
            Keyword: 17,
        },
        CompletionItemInsertTextRule: { InsertAsSnippet: 4 },
        registerCompletionItemProvider: vi.fn(() => ({ dispose: vi.fn() })),
        registerHoverProvider: vi.fn(() => ({ dispose: vi.fn() })),
        registerSignatureHelpProvider: vi.fn(() => ({ dispose: vi.fn() })),
    },
} as unknown as typeof Monaco;

beforeEach(() => {
    sockets.length = 0;
    vi.stubGlobal('WebSocket', FakeWebSocket);
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                new Response(JSON.stringify({ port: 54213 }), { status: 200 }),
        ),
    );
});

afterEach(() => vi.unstubAllGlobals());

async function connectAndHandshake(): Promise<FakeWebSocket> {
    await vi.waitFor(() => expect(sockets).toHaveLength(1));
    const socket = sockets[0]!;
    socket.open();

    await vi.waitFor(() => expect(socket.sent).toHaveLength(1));
    const initialize = JSON.parse(socket.sent[0]!) as { id: number };
    socket.receive({ jsonrpc: '2.0', id: initialize.id, result: {} });

    return socket;
}

it('requests a port for the project and opens a WebSocket to it', async () => {
    const attaching = attachLanguageServer(monaco, 'customer-portal', '<?php');
    const socket = await connectAndHandshake();

    await attaching;

    expect(fetch).toHaveBeenCalledWith(
        '/api/projects/customer-portal/language-server',
        expect.objectContaining({ method: 'POST' }),
    );
    expect(socket.url).toBe('wss://tinkerbench.test:54213');
});

it('sends the initial document content once the connection is ready', async () => {
    const attaching = attachLanguageServer(
        monaco,
        'customer-portal',
        '<?php echo 1;',
    );
    const socket = await connectAndHandshake();
    await attaching;

    const didOpen = socket.sent
        .map((raw) => JSON.parse(raw) as { method?: string; params?: unknown })
        .find((message) => message.method === 'textDocument/didOpen');

    expect(didOpen?.params).toMatchObject({
        textDocument: { text: '<?php echo 1;' },
    });
});

it('closes the socket and disposes the registered providers on dispose', async () => {
    const attaching = attachLanguageServer(monaco, 'customer-portal', '<?php');
    const socket = await connectAndHandshake();
    const handle = await attaching;

    handle.dispose();

    expect(socket.readyState).toBe(3);
});

it('marks the completion list incomplete when intelephense does, so Monaco re-queries on the next keystroke', async () => {
    const attaching = attachLanguageServer(monaco, 'customer-portal', '<?php');
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerCompletionItemProvider)
        .mock.calls.at(-1)![1];
    const completing = provider.provideCompletionItems(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
        {} as never,
    );

    await vi.waitFor(() =>
        expect(
            socket.sent.some(
                (raw) =>
                    (JSON.parse(raw) as { method?: string }).method ===
                    'textDocument/completion',
            ),
        ).toBe(true),
    );
    const completionRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'textDocument/completion')!;
    socket.receive({
        jsonrpc: '2.0',
        id: completionRequest.id,
        result: { isIncomplete: true, items: [{ label: 'Str' }] },
    });

    expect(await completing).toMatchObject({ incomplete: true });
});
