import { render } from '@testing-library/vue';
import * as monaco from 'monaco-editor';
import { beforeEach, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { attachLanguageServer } from '@/lib/languageServer';
import MonacoEditor from './MonacoEditor.vue';

// useTheme has its own test (useTheme.test.ts) proving system-preference fallback,
// persistence, and the dark-class toggle; replaced here so this test only proves
// MonacoEditor.vue reads/reacts to its theme value correctly.
const mockTheme = ref<'light' | 'dark'>('dark');

vi.mock('@/composables/useTheme', () => ({
    useTheme: () => ({ theme: mockTheme }),
}));

// createEditorWorker() constructs a real Web Worker (via Vite's `?worker` import), which
// jsdom can't instantiate; environment-driven, not a "tested elsewhere" mock.
vi.mock('@/lib/monacoEditorWorker', () => ({
    createEditorWorker: vi.fn(),
}));

const intelephenseHandle = {
    dispose: vi.fn(),
    notifyContentChanged: vi.fn(),
};
const laravelLspHandle = {
    dispose: vi.fn(),
    notifyContentChanged: vi.fn(),
};

// attachLanguageServer has its own test (languageServer.test.ts) proving the LSP handshake
// and provider behavior; replaced here so this test only proves MonacoEditor.vue calls it
// with the right arguments and reacts correctly to its resolved handle. Resolves per call
// based on the config's ownerKey, since MonacoEditor.vue now calls it once per server.
vi.mock('@/lib/languageServer', () => ({
    attachLanguageServer: vi.fn(),
}));

const onDidChangeModelContent = vi.fn();
const addAction = vi.fn();
const model = {};
const editor = {
    addAction,
    dispose: vi.fn(),
    focus: vi.fn(),
    getModel: vi.fn(() => model),
    getValue: vi.fn(() => '<?php echo "changed";'),
    layout: vi.fn(),
    onDidChangeModelContent,
    revealLineInCenter: vi.fn(),
    setPosition: vi.fn(),
};

// The real monaco-editor needs a full DOM/canvas rendering surface jsdom doesn't provide;
// environment-driven, not a "tested elsewhere" mock.
vi.mock('monaco-editor', () => ({
    editor: {
        create: vi.fn(() => editor),
        defineTheme: vi.fn(),
        setTheme: vi.fn(),
    },
    KeyCode: { Enter: 3 },
    KeyMod: { CtrlCmd: 2048 },
}));

beforeEach(() => {
    mockTheme.value = 'dark';
    addAction.mockClear();
    editor.dispose.mockClear();
    editor.focus.mockClear();
    editor.getValue.mockClear();
    editor.layout.mockClear();
    editor.revealLineInCenter.mockClear();
    editor.setPosition.mockClear();
    onDidChangeModelContent.mockClear();
    intelephenseHandle.dispose.mockClear();
    intelephenseHandle.notifyContentChanged.mockClear();
    laravelLspHandle.dispose.mockClear();
    laravelLspHandle.notifyContentChanged.mockClear();
    vi.mocked(monaco.editor.create).mockClear();
    vi.mocked(monaco.editor.defineTheme).mockClear();
    vi.mocked(monaco.editor.setTheme).mockClear();
    vi.mocked(attachLanguageServer)
        .mockReset()
        .mockImplementation(async (_monaco, config) =>
            config.ownerKey === 'laravel-lsp'
                ? laravelLspHandle
                : intelephenseHandle,
        );
});

function actionRun(id: string): () => void {
    const action = addAction.mock.calls.find((call) => call[0].id === id)?.[0];

    if (!action) {
        throw new Error(`No action registered with id "${id}"`);
    }

    return action.run;
}

it('creates the editor with PHP syntax highlighting and the github-dark theme', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(monaco.editor.defineTheme).toHaveBeenCalledWith(
        'github-dark',
        expect.any(Object),
    );
    expect(monaco.editor.create).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({
            value: '<?php echo "initial";',
            language: 'php',
            theme: 'github-dark',
        }),
    );
});

it('hides diagnostic markers from the overview ruler, keeping only the inline squiggly underline', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(monaco.editor.create).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({ overviewRulerLanes: 0 }),
    );
});

it('creates the editor with the rendering and interaction options mirrored from the user VS Code settings', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(monaco.editor.create).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({
            lineHeight: 28,
            scrollbar: { vertical: 'hidden', horizontal: 'hidden' },
            scrollBeyondLastLine: false,
            overviewRulerLanes: 0,
            renderLineHighlight: 'none',
            occurrencesHighlight: 'off',
            selectionHighlight: false,
            matchBrackets: 'never',
            bracketPairColorization: { enabled: false },
            guides: { indentation: false },
            colorDecorators: false,
            detectIndentation: false,
            snippetSuggestions: 'top',
            linkedEditing: true,
            emptySelectionClipboard: false,
            copyWithSyntaxHighlighting: false,
        }),
    );
});

it('defines a github-light theme alongside github-dark', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(monaco.editor.defineTheme).toHaveBeenCalledWith(
        'github-light',
        expect.any(Object),
    );
});

it('creates the editor with the github-light theme when the light theme is active', () => {
    mockTheme.value = 'light';

    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(monaco.editor.create).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({ theme: 'github-light' }),
    );
});

it('switches the editor theme when the app theme changes', async () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    mockTheme.value = 'light';
    await vi.waitFor(() =>
        expect(monaco.editor.setTheme).toHaveBeenCalledWith('github-light'),
    );
});

it('emits change with the current content when the editor content changes', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(rendered.emitted().change).toEqual([['<?php echo "changed";']]);
});

it('disposes the editor when unmounted', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    rendered.unmount();

    expect(editor.dispose).toHaveBeenCalledOnce();
});

it('emits run when the Ctrl/Cmd+Enter action runs', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    actionRun('tinkerbench.run')();

    expect(rendered.emitted().run).toHaveLength(1);
    expect(addAction).toHaveBeenCalledWith(
        expect.objectContaining({
            keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter],
        }),
    );
});

async function attachedHandles(): Promise<void> {
    await Promise.all(
        vi
            .mocked(attachLanguageServer)
            .mock.results.map((result) => result.value.catch(() => undefined)),
    );
}

it('attaches intelephense for the current project', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(attachLanguageServer).toHaveBeenCalledWith(
        monaco,
        {
            requestPortUrl: '/api/projects/my-project/language-server',
            ownerKey: 'intelephense',
        },
        '<?php echo "initial";',
        model,
    );
});

it('attaches laravel-lsp for the current project', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(attachLanguageServer).toHaveBeenCalledWith(
        monaco,
        {
            requestPortUrl: '/api/projects/my-project/laravel-language-server',
            ownerKey: 'laravel-lsp',
        },
        '<?php echo "initial";',
        model,
    );
});

it('forwards content changes to both language servers once attached', async () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await attachedHandles();
    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(intelephenseHandle.notifyContentChanged).toHaveBeenCalledWith(
        '<?php echo "changed";',
    );
    expect(laravelLspHandle.notifyContentChanged).toHaveBeenCalledWith(
        '<?php echo "changed";',
    );
});

it('disposes both language servers once attached and the editor unmounts', async () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await attachedHandles();
    rendered.unmount();

    expect(intelephenseHandle.dispose).toHaveBeenCalledOnce();
    expect(laravelLspHandle.dispose).toHaveBeenCalledOnce();
});

it('disposes a language server immediately if it resolves after the editor already unmounted', async () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    rendered.unmount();
    await attachedHandles();

    expect(intelephenseHandle.dispose).toHaveBeenCalledOnce();
    expect(laravelLspHandle.dispose).toHaveBeenCalledOnce();
});

it('keeps the editor usable when both language servers fail to attach', async () => {
    vi.mocked(attachLanguageServer)
        .mockReset()
        .mockRejectedValue(new Error('boom'));

    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await attachedHandles();
    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(rendered.emitted().change).toEqual([['<?php echo "changed";']]);
});

it('still attaches intelephense when laravel-lsp fails to attach', async () => {
    vi.mocked(attachLanguageServer)
        .mockReset()
        .mockImplementation(async (_monaco, config) => {
            if (config.ownerKey === 'laravel-lsp') {
                throw new Error('laravel-lsp unavailable');
            }

            return intelephenseHandle;
        });

    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await attachedHandles();
    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(intelephenseHandle.notifyContentChanged).toHaveBeenCalledWith(
        '<?php echo "changed";',
    );
});

it('reveals and focuses a line when revealLine is called', () => {
    let instance: { revealLine: (line: number) => void } | undefined;

    const Harness = defineComponent({
        render() {
            return h(MonacoEditor, {
                initialValue: '<?php',
                project: 'my-project',
                ref: (component: unknown) => {
                    instance = component as {
                        revealLine: (line: number) => void;
                    };
                },
            });
        },
    });

    render(Harness);
    editor.focus.mockClear();

    instance?.revealLine(7);

    expect(editor.revealLineInCenter).toHaveBeenCalledWith(7);
    expect(editor.setPosition).toHaveBeenCalledWith({
        lineNumber: 7,
        column: 1,
    });
    expect(editor.focus).toHaveBeenCalled();
});
