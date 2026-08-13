import type * as Monaco from 'monaco-editor';
import StartLanguageServerController from '@/actions/Application/Projects/Controllers/StartLanguageServerController';
import { xsrfHeader } from '@/lib/csrf';

const DOCUMENT_URI = 'file:///tinkerbench-snippet.php';

interface LspPosition {
    character: number;
    line: number;
}

interface LspRange {
    end: LspPosition;
    start: LspPosition;
}

interface LspTextEdit {
    newText: string;
    range: LspRange;
}

interface LspCompletionItem {
    additionalTextEdits?: LspTextEdit[];
    data?: unknown;
    detail?: string;
    documentation?: { value: string } | string;
    insertText?: string;
    insertTextFormat?: number;
    kind?: number;
    label: string;
    textEdit?: LspTextEdit;
}

interface LspMarkupContent {
    value: string;
}

interface LspHover {
    contents: LspMarkupContent | LspMarkupContent[] | string | string[];
}

interface LspSignatureInformation {
    documentation?: { value: string } | string;
    label: string;
    parameters?: { label: string }[];
}

interface LspSignatureHelp {
    activeParameter?: number;
    activeSignature?: number;
    signatures: LspSignatureInformation[];
}

interface JsonRpcMessage {
    error?: { message: string };
    id?: number;
    method?: string;
    params?: unknown;
    result?: unknown;
}

interface MonacoPosition {
    column: number;
    lineNumber: number;
}

interface MonacoRange {
    endColumn: number;
    endLineNumber: number;
    startColumn: number;
    startLineNumber: number;
}

export interface LanguageServerHandle {
    dispose(): void;
    notifyContentChanged(content: string): void;
}

async function requestPort(project: string): Promise<number> {
    const response = await fetch(StartLanguageServerController.url(project), {
        method: 'POST',
        headers: xsrfHeader(),
    });
    const body = (await response.json()) as { port: number };

    return body.port;
}

function toPlainText(
    content:
        LspMarkupContent | LspMarkupContent[] | string | string[] | undefined,
): string {
    if (content === undefined) {
        return '';
    }

    if (typeof content === 'string') {
        return content;
    }

    if (Array.isArray(content)) {
        return content
            .map((entry) => (typeof entry === 'string' ? entry : entry.value))
            .join('\n\n');
    }

    return content.value;
}

function toAdditionalTextEdits(
    edits: LspTextEdit[] | undefined,
): { range: MonacoRange; text: string }[] | undefined {
    return edits?.map((edit) => ({
        range: toMonacoRange(edit.range, { lineNumber: 1, column: 1 }),
        text: edit.newText,
    }));
}

function toMonacoRange(
    range: LspRange | undefined,
    position: MonacoPosition,
): MonacoRange {
    if (!range) {
        return {
            startLineNumber: position.lineNumber,
            startColumn: position.column,
            endLineNumber: position.lineNumber,
            endColumn: position.column,
        };
    }

    return {
        startLineNumber: range.start.line + 1,
        startColumn: range.start.character + 1,
        endLineNumber: range.end.line + 1,
        endColumn: range.end.character + 1,
    };
}

export async function attachLanguageServer(
    monaco: typeof Monaco,
    project: string,
    initialContent: string,
): Promise<LanguageServerHandle> {
    const port = await requestPort(project);
    // Safari (macOS 15+) blocks a plain ws:// connection from an https:// page as mixed content, even to
    // 127.0.0.1, unlike Chrome/Firefox. wss:// against tinkerbench.test itself (not window.location.hostname,
    // browser tests may serve the page from a plain http://127.0.0.1 test server) matches the certificate the
    // bridge presents (Herd's already-trusted tinkerbench.test certificate), so it works everywhere: an
    // insecure page opening a secure WebSocket to a different host is not a mixed-content downgrade.
    const socket = new WebSocket(`wss://tinkerbench.test:${port}`);

    // LSP CompletionItemKind numbers (per the LSP spec) mapped to Monaco's own enum values, the completion
    // kinds intelephense actually returns in practice.
    const completionKindByLspKind: Record<number, number> = {
        2: monaco.languages.CompletionItemKind.Method,
        3: monaco.languages.CompletionItemKind.Function,
        5: monaco.languages.CompletionItemKind.Field,
        6: monaco.languages.CompletionItemKind.Variable,
        7: monaco.languages.CompletionItemKind.Class,
        9: monaco.languages.CompletionItemKind.Module,
        10: monaco.languages.CompletionItemKind.Property,
        14: monaco.languages.CompletionItemKind.Keyword,
    };

    let nextId = 0;
    let documentVersion = 1;
    const pending = new Map<number, (result: unknown) => void>();

    function send(message: Record<string, unknown>): void {
        socket.send(JSON.stringify({ jsonrpc: '2.0', ...message }));
    }

    function notify(method: string, params: unknown): void {
        send({ method, params });
    }

    function request(method: string, params: unknown): Promise<unknown> {
        return new Promise((resolve) => {
            const id = ++nextId;
            pending.set(id, resolve);
            send({ id, method, params });
        });
    }

    socket.addEventListener('message', (event) => {
        const message = JSON.parse(event.data as string) as JsonRpcMessage;

        if (message.id !== undefined && pending.has(message.id)) {
            pending.get(message.id)?.(message.error ? null : message.result);
            pending.delete(message.id);
        }
    });

    await new Promise<void>((resolve) =>
        socket.addEventListener('open', () => resolve()),
    );

    // Declares roughly what a real editor's LSP client (e.g. vscode-languageclient) already declares, so
    // intelephense behaves the same way here as it does in VS Code: snippet-formatted completions (parameter
    // placeholders), resolve() for auto-import edits it otherwise omits from the bulk list, plaintext docs
    // (no markdown support wired up here, so asking for markdown would just leak raw ** and ` syntax).
    await request('initialize', {
        processId: null,
        rootUri: null,
        capabilities: {
            textDocument: {
                completion: {
                    completionItem: {
                        snippetSupport: true,
                        documentationFormat: ['plaintext'],
                        resolveSupport: {
                            properties: [
                                'documentation',
                                'detail',
                                'additionalTextEdits',
                            ],
                        },
                    },
                },
                hover: { contentFormat: ['plaintext'] },
                signatureHelp: {
                    signatureInformation: {
                        documentationFormat: ['plaintext'],
                    },
                },
            },
        },
    });
    notify('initialized', {});
    notify('textDocument/didOpen', {
        textDocument: {
            uri: DOCUMENT_URI,
            languageId: 'php',
            version: documentVersion,
            text: initialContent,
        },
    });

    const lspItemsByMonacoItem = new WeakMap<object, LspCompletionItem>();
    const SNIPPET_FORMAT = 2;

    const completionProvider = monaco.languages.registerCompletionItemProvider(
        'php',
        {
            triggerCharacters: ['$', '>', ':'],
            async provideCompletionItems(model, position) {
                const result = (await request('textDocument/completion', {
                    textDocument: { uri: DOCUMENT_URI },
                    position: {
                        line: position.lineNumber - 1,
                        character: position.column - 1,
                    },
                })) as
                    | { isIncomplete?: boolean; items?: LspCompletionItem[] }
                    | LspCompletionItem[]
                    | null;

                const items = Array.isArray(result)
                    ? result
                    : (result?.items ?? []);

                return {
                    // intelephense marks most real completion lists incomplete (it caps how many symbols it
                    // returns per request), so without this Monaco reuses and client-side-filters this one
                    // response for every later keystroke instead of asking intelephense again with the fuller
                    // prefix, and a genuinely matching class can be missing from that first, broader batch.
                    incomplete:
                        !Array.isArray(result) && result?.isIncomplete === true,
                    suggestions: items.map((item) => {
                        const monacoItem = {
                            label: item.label,
                            kind:
                                completionKindByLspKind[item.kind ?? 0] ??
                                monaco.languages.CompletionItemKind.Text,
                            documentation:
                                toPlainText(item.documentation) || undefined,
                            insertText:
                                item.textEdit?.newText ??
                                item.insertText ??
                                item.label,
                            insertTextRules:
                                item.insertTextFormat === SNIPPET_FORMAT
                                    ? monaco.languages
                                          .CompletionItemInsertTextRule
                                          .InsertAsSnippet
                                    : undefined,
                            range: toMonacoRange(
                                item.textEdit?.range,
                                position,
                            ),
                            // intelephense often already includes the matching `use` statement edit here for
                            // an unimported class, without needing a separate resolve() round trip.
                            additionalTextEdits: toAdditionalTextEdits(
                                item.additionalTextEdits,
                            ),
                        };

                        // Keeps the original item around for resolveCompletionItem: LSP's completionItem/resolve
                        // expects the same (often server-annotated) item back, not just its rendered label.
                        lspItemsByMonacoItem.set(monacoItem, item);

                        return monacoItem;
                    }),
                };
            },
            async resolveCompletionItem(item) {
                const original = lspItemsByMonacoItem.get(item);

                if (!original) {
                    return item;
                }

                const resolved = (await request(
                    'completionItem/resolve',
                    original,
                )) as LspCompletionItem | null;

                if (!resolved) {
                    return item;
                }

                return {
                    ...item,
                    documentation:
                        toPlainText(resolved.documentation) ||
                        item.documentation,
                    // additionalTextEdits is how intelephense inserts the matching `use` statement when you
                    // accept a completion for a class that isn't imported yet, same as it does in VS Code.
                    // Falls back to whatever provideCompletionItems already had: resolve() isn't guaranteed to
                    // repeat data the bulk list already included.
                    additionalTextEdits:
                        toAdditionalTextEdits(resolved.additionalTextEdits) ??
                        item.additionalTextEdits,
                };
            },
        },
    );

    const hoverProvider = monaco.languages.registerHoverProvider('php', {
        async provideHover(model, position) {
            const result = (await request('textDocument/hover', {
                textDocument: { uri: DOCUMENT_URI },
                position: {
                    line: position.lineNumber - 1,
                    character: position.column - 1,
                },
            })) as LspHover | null;

            if (!result) {
                return null;
            }

            const value = toPlainText(result.contents);

            return value === '' ? null : { contents: [{ value }] };
        },
    });

    const signatureHelpProvider =
        monaco.languages.registerSignatureHelpProvider('php', {
            signatureHelpTriggerCharacters: ['(', ','],
            async provideSignatureHelp(model, position) {
                const result = (await request('textDocument/signatureHelp', {
                    textDocument: { uri: DOCUMENT_URI },
                    position: {
                        line: position.lineNumber - 1,
                        character: position.column - 1,
                    },
                })) as LspSignatureHelp | null;

                if (!result || result.signatures.length === 0) {
                    return null;
                }

                return {
                    dispose(): void {},
                    value: {
                        activeSignature: result.activeSignature ?? 0,
                        activeParameter: result.activeParameter ?? 0,
                        signatures: result.signatures.map((signature) => ({
                            label: signature.label,
                            documentation:
                                toPlainText(signature.documentation) ||
                                undefined,
                            parameters: signature.parameters ?? [],
                        })),
                    },
                };
            },
        });

    return {
        dispose(): void {
            completionProvider.dispose();
            hoverProvider.dispose();
            signatureHelpProvider.dispose();
            socket.close();
        },
        notifyContentChanged(content: string): void {
            documentVersion += 1;
            notify('textDocument/didChange', {
                textDocument: { uri: DOCUMENT_URI, version: documentVersion },
                contentChanges: [{ text: content }],
            });
        },
    };
}
