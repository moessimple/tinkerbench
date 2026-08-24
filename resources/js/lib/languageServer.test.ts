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
        options?: { once?: boolean },
    ): void {
        const listener = options?.once
            ? (event: { data?: string }) => {
                  this.removeListener(type, listener);
                  callback(event);
              }
            : callback;

        (this.listeners[type] ??= []).push(listener);
    }

    send(data: string): void {
        this.sent.push(data);
    }

    close(): void {
        this.readyState = 3;
        this.listeners.close?.forEach((callback) => callback({}));
    }

    open(): void {
        this.readyState = FakeWebSocket.OPEN;
        this.listeners.open?.forEach((callback) => callback({}));
    }

    error(): void {
        this.listeners.error?.forEach((callback) => callback({}));
    }

    receive(message: unknown): void {
        this.listeners.message?.forEach((callback) =>
            callback({ data: JSON.stringify(message) }),
        );
    }

    private removeListener(
        type: string,
        listener: (event: { data?: string }) => void,
    ): void {
        this.listeners[type] = (this.listeners[type] ?? []).filter(
            (registered) => registered !== listener,
        );
    }
}

const model = {} as Monaco.editor.ITextModel;

const intelephenseConfig = {
    requestPortUrl: '/api/projects/customer-portal/language-server',
    ownerKey: 'intelephense',
};

const monaco = {
    editor: {
        setModelMarkers: vi.fn(),
    },
    MarkerSeverity: { Hint: 1, Info: 2, Warning: 4, Error: 8 },
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
    vi.mocked(monaco.editor.setModelMarkers).mockClear();
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

async function connectAndHandshake(
    initializeResult: unknown = {},
): Promise<FakeWebSocket> {
    await vi.waitFor(() => expect(sockets).toHaveLength(1));
    const socket = sockets[0]!;
    socket.open();

    await vi.waitFor(() => expect(socket.sent).toHaveLength(1));
    const initialize = JSON.parse(socket.sent[0]!) as { id: number };
    socket.receive({
        jsonrpc: '2.0',
        id: initialize.id,
        result: initializeResult,
    });

    return socket;
}

it('requests a port for the project and opens a WebSocket to it', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
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
        intelephenseConfig,
        '<?php echo 1;',
        model,
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
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    const handle = await attaching;

    handle.dispose();

    expect(socket.readyState).toBe(3);
});

it('marks the completion list incomplete when intelephense does, so Monaco re-queries on the next keystroke', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
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

it('rejects when the port endpoint responds with an error status', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response('', { status: 500 })),
    );

    await expect(
        attachLanguageServer(monaco, intelephenseConfig, '<?php', model),
    ).rejects.toThrow('Unable to start the language server (500).');
});

it('rejects when the port endpoint response has no numeric port', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response(JSON.stringify({}), { status: 200 })),
    );

    await expect(
        attachLanguageServer(monaco, intelephenseConfig, '<?php', model),
    ).rejects.toThrow('The language server did not report a port.');
});

it('rejects when the websocket errors before the connection opens', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    await vi.waitFor(() => expect(sockets).toHaveLength(1));

    sockets[0]!.error();

    await expect(attaching).rejects.toThrow(
        'Unable to connect to the language server.',
    );
});

it('rejects a pending request instead of leaving it stuck forever when the connection closes', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    const handle = await attaching;

    const provider = vi
        .mocked(monaco.languages.registerHoverProvider)
        .mock.calls.at(-1)![1];
    const hovering = provider.provideHover(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
    );

    socket.close();

    await expect(hovering).rejects.toThrow(
        'The language server connection closed.',
    );
    expect(handle).toBeDefined();
});

it('rejects pending requests immediately on dispose instead of waiting for the socket to close', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    const handle = await attaching;

    const provider = vi
        .mocked(monaco.languages.registerHoverProvider)
        .mock.calls.at(-1)![1];
    const hovering = provider.provideHover(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
    );

    handle.dispose();

    await expect(hovering).rejects.toThrow('The language server was disposed.');
    expect(socket.sent.length).toBeGreaterThan(0);
});

it('maps a completion item into the shape Monaco expects, including snippet insertion and an auto-import edit', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerCompletionItemProvider)
        .mock.calls.at(-1)![1];
    const completing = provider.provideCompletionItems(
        { getValue: () => '' } as never,
        { lineNumber: 3, column: 5 } as never,
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
        result: {
            items: [
                {
                    label: 'strlen',
                    kind: 3,
                    insertTextFormat: 2,
                    textEdit: {
                        newText: 'strlen(${1:string})',
                        range: {
                            start: { line: 2, character: 0 },
                            end: { line: 2, character: 4 },
                        },
                    },
                    additionalTextEdits: [
                        {
                            newText: 'use App\\Str;\n',
                            range: {
                                start: { line: 0, character: 0 },
                                end: { line: 0, character: 0 },
                            },
                        },
                    ],
                },
            ],
        },
    });

    const { suggestions } = (await completing) as { suggestions: unknown[] };
    expect(suggestions).toEqual([
        expect.objectContaining({
            label: 'strlen',
            kind: monaco.languages.CompletionItemKind.Function,
            insertText: 'strlen(${1:string})',
            insertTextRules:
                monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
            range: {
                startLineNumber: 3,
                startColumn: 1,
                endLineNumber: 3,
                endColumn: 5,
            },
            additionalTextEdits: [
                {
                    text: 'use App\\Str;\n',
                    range: {
                        startLineNumber: 1,
                        startColumn: 1,
                        endLineNumber: 1,
                        endColumn: 1,
                    },
                },
            ],
        }),
    ]);
});

it('resolves a completion item with the server-provided documentation and import edits', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
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
        result: { items: [{ label: 'strlen' }] },
    });
    const { suggestions } = (await completing) as { suggestions: unknown[] };

    const resolving = provider.resolveCompletionItem!(
        suggestions[0] as never,
        {} as never,
    );
    await vi.waitFor(() =>
        expect(
            socket.sent.some(
                (raw) =>
                    (JSON.parse(raw) as { method?: string }).method ===
                    'completionItem/resolve',
            ),
        ).toBe(true),
    );
    const resolveRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'completionItem/resolve')!;
    socket.receive({
        jsonrpc: '2.0',
        id: resolveRequest.id,
        result: {
            label: 'strlen',
            documentation: 'Returns the length of a string.',
            additionalTextEdits: [
                {
                    newText: 'use App\\Str;\n',
                    range: {
                        start: { line: 0, character: 0 },
                        end: { line: 0, character: 0 },
                    },
                },
            ],
        },
    });

    const resolved = (await resolving) as {
        additionalTextEdits: unknown[];
        documentation: { value: string };
    };
    expect(resolved.documentation).toEqual({
        value: 'Returns the length of a string.',
    });
    expect(resolved.additionalTextEdits).toEqual([
        {
            text: 'use App\\Str;\n',
            range: {
                startLineNumber: 1,
                startColumn: 1,
                endLineNumber: 1,
                endColumn: 1,
            },
        },
    ]);
});

it('returns the hover contents reported by the language server', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerHoverProvider)
        .mock.calls.at(-1)![1];
    const hovering = provider.provideHover(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
    );
    await vi.waitFor(() =>
        expect(
            socket.sent.some(
                (raw) =>
                    (JSON.parse(raw) as { method?: string }).method ===
                    'textDocument/hover',
            ),
        ).toBe(true),
    );
    const hoverRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'textDocument/hover')!;
    socket.receive({
        jsonrpc: '2.0',
        id: hoverRequest.id,
        result: { contents: 'function strlen(string $s): int' },
    });

    expect(await hovering).toEqual({
        contents: [{ value: 'function strlen(string $s): int' }],
    });
});

it('returns no hover when the language server has nothing to show', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerHoverProvider)
        .mock.calls.at(-1)![1];
    const hovering = provider.provideHover(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
    );
    await vi.waitFor(() =>
        expect(
            socket.sent.some(
                (raw) =>
                    (JSON.parse(raw) as { method?: string }).method ===
                    'textDocument/hover',
            ),
        ).toBe(true),
    );
    const hoverRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'textDocument/hover')!;
    socket.receive({ jsonrpc: '2.0', id: hoverRequest.id, result: null });

    expect(await hovering).toBeNull();
});

it('returns the active signature and parameter reported by the language server', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerSignatureHelpProvider)
        .mock.calls.at(-1)![1];
    const helping = provider.provideSignatureHelp(
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
                    'textDocument/signatureHelp',
            ),
        ).toBe(true),
    );
    const signatureRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'textDocument/signatureHelp')!;
    socket.receive({
        jsonrpc: '2.0',
        id: signatureRequest.id,
        result: {
            activeSignature: 0,
            activeParameter: 1,
            signatures: [
                {
                    label: 'strlen(string $string): int',
                    documentation: 'Returns the length of a string.',
                    parameters: [{ label: 'string $string' }],
                },
            ],
        },
    });

    expect((await helping)?.value).toEqual({
        activeSignature: 0,
        activeParameter: 1,
        signatures: [
            {
                label: 'strlen(string $string): int',
                documentation: { value: 'Returns the length of a string.' },
                parameters: [{ label: 'string $string' }],
            },
        ],
    });
});

it('returns no signature help when the language server has no matching signature', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const provider = vi
        .mocked(monaco.languages.registerSignatureHelpProvider)
        .mock.calls.at(-1)![1];
    const helping = provider.provideSignatureHelp(
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
                    'textDocument/signatureHelp',
            ),
        ).toBe(true),
    );
    const signatureRequest = socket.sent
        .map((raw) => JSON.parse(raw) as { id: number; method?: string })
        .find((message) => message.method === 'textDocument/signatureHelp')!;
    socket.receive({
        jsonrpc: '2.0',
        id: signatureRequest.id,
        result: { signatures: [] },
    });

    expect(await helping).toBeNull();
});

it('rejects a request that times out waiting for a response', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    await connectAndHandshake();
    await attaching;

    vi.useFakeTimers();
    const provider = vi
        .mocked(monaco.languages.registerHoverProvider)
        .mock.calls.at(-1)![1];
    const hovering = provider.provideHover(
        { getValue: () => '' } as never,
        { lineNumber: 1, column: 1 } as never,
        {} as never,
    );
    const assertion = expect(hovering).rejects.toThrow(
        'Timed out waiting for a response to "textDocument/hover".',
    );

    await vi.advanceTimersByTimeAsync(10_000);
    await assertion;
    vi.useRealTimers();
});

it('declares support for diagnostics when initializing', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const initialize = JSON.parse(socket.sent[0]!) as {
        params: {
            capabilities: {
                textDocument: { publishDiagnostics?: unknown };
            };
        };
    };

    expect(
        initialize.params.capabilities.textDocument.publishDiagnostics,
    ).toEqual({});
});

it('applies diagnostics from the language server as Monaco markers', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    socket.receive({
        jsonrpc: '2.0',
        method: 'textDocument/publishDiagnostics',
        params: {
            uri: 'file:///tinkerbench-snippet.php',
            diagnostics: [
                {
                    range: {
                        start: { line: 0, character: 5 },
                        end: { line: 0, character: 10 },
                    },
                    severity: 2,
                    message: "Undefined method 'wehre'.",
                },
            ],
        },
    });

    expect(monaco.editor.setModelMarkers).toHaveBeenCalledWith(
        model,
        'intelephense',
        [
            {
                startLineNumber: 1,
                startColumn: 6,
                endLineNumber: 1,
                endColumn: 11,
                severity: monaco.MarkerSeverity.Warning,
                message: "Undefined method 'wehre'.",
            },
        ],
    );
});

it('defaults a diagnostic with no reported severity to an error marker', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    socket.receive({
        jsonrpc: '2.0',
        method: 'textDocument/publishDiagnostics',
        params: {
            uri: 'file:///tinkerbench-snippet.php',
            diagnostics: [
                {
                    range: {
                        start: { line: 0, character: 0 },
                        end: { line: 0, character: 1 },
                    },
                    message: 'Syntax error.',
                },
            ],
        },
    });

    expect(monaco.editor.setModelMarkers).toHaveBeenCalledWith(
        model,
        'intelephense',
        [expect.objectContaining({ severity: monaco.MarkerSeverity.Error })],
    );
});

it('clears markers when disposed', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    await connectAndHandshake();
    const handle = await attaching;

    handle.dispose();

    expect(monaco.editor.setModelMarkers).toHaveBeenCalledWith(
        model,
        'intelephense',
        [],
    );
});

it("registers the completion provider with the server's own declared trigger characters", async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    // A stale, hardcoded trigger-character list is exactly what caused this: typing "." after
    // "app" inside config('app. never re-queried the server for narrowed keys, since "." wasn't
    // in a list written for intelephense's own needs. A real LSP client always uses what the
    // server itself declares in its initialize response instead.
    await connectAndHandshake({
        capabilities: {
            completionProvider: { triggerCharacters: ['"', "'", '.', '|'] },
        },
    });
    await attaching;

    expect(
        monaco.languages.registerCompletionItemProvider,
    ).toHaveBeenCalledWith(
        'php',
        expect.objectContaining({ triggerCharacters: ['"', "'", '.', '|'] }),
    );
});

it('registers the completion provider with no trigger characters when the server declares none', async () => {
    const attaching = attachLanguageServer(
        monaco,
        intelephenseConfig,
        '<?php',
        model,
    );
    await connectAndHandshake({});
    await attaching;

    expect(
        monaco.languages.registerCompletionItemProvider,
    ).toHaveBeenCalledWith(
        'php',
        expect.objectContaining({
            triggerCharacters: [],
        }),
    );
});

it('requests a port from the configured URL and applies diagnostics under the configured owner key', async () => {
    const attaching = attachLanguageServer(
        monaco,
        {
            requestPortUrl:
                '/api/projects/customer-portal/laravel-language-server',
            ownerKey: 'laravel-lsp',
        },
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    expect(fetch).toHaveBeenCalledWith(
        '/api/projects/customer-portal/laravel-language-server',
        expect.objectContaining({ method: 'POST' }),
    );

    socket.receive({
        jsonrpc: '2.0',
        method: 'textDocument/publishDiagnostics',
        params: {
            uri: 'file:///tinkerbench-snippet.php',
            diagnostics: [
                {
                    range: {
                        start: { line: 0, character: 0 },
                        end: { line: 0, character: 1 },
                    },
                    message: 'Route [missing.route] not defined.',
                },
            ],
        },
    });

    expect(monaco.editor.setModelMarkers).toHaveBeenCalledWith(
        model,
        'laravel-lsp',
        [
            expect.objectContaining({
                message: 'Route [missing.route] not defined.',
            }),
        ],
    );
});

it('merges the configured initializationOptions into the initialize request', async () => {
    const attaching = attachLanguageServer(
        monaco,
        {
            requestPortUrl:
                '/api/projects/customer-portal/laravel-language-server',
            ownerKey: 'laravel-lsp',
            initializationOptions: {
                phpEnvironment: 'herd',
                pestGenerateDocBlocks: false,
            },
        },
        '<?php',
        model,
    );
    const socket = await connectAndHandshake();
    await attaching;

    const initialize = JSON.parse(socket.sent[0]!) as {
        params: { initializationOptions?: unknown };
    };

    expect(initialize.params.initializationOptions).toEqual({
        phpEnvironment: 'herd',
        pestGenerateDocBlocks: false,
    });
});
