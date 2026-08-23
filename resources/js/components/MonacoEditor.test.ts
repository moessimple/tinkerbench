import { render } from '@testing-library/vue';
import * as monaco from 'monaco-editor';
import { beforeEach, expect, it, vi } from 'vitest';
import { attachLanguageServer } from '@/lib/languageServer';
import MonacoEditor from './MonacoEditor.vue';

// createEditorWorker() constructs a real Web Worker (via Vite's `?worker` import), which
// jsdom can't instantiate; environment-driven, not a "tested elsewhere" mock.
vi.mock('@/lib/monacoEditorWorker', () => ({
    createEditorWorker: vi.fn(),
}));

const languageServerHandle = {
    dispose: vi.fn(),
    notifyContentChanged: vi.fn(),
};

// attachLanguageServer has its own test (languageServer.test.ts) proving the LSP handshake
// and provider behavior; replaced here so this test only proves MonacoEditor.vue calls it
// with the right arguments and reacts correctly to its resolved handle.
vi.mock('@/lib/languageServer', () => ({
    attachLanguageServer: vi.fn(),
}));

const onDidChangeModelContent = vi.fn();
const addAction = vi.fn();
const editor = {
    addAction,
    dispose: vi.fn(),
    focus: vi.fn(),
    getValue: vi.fn(() => '<?php echo "changed";'),
    layout: vi.fn(),
    onDidChangeModelContent,
};

// The real monaco-editor needs a full DOM/canvas rendering surface jsdom doesn't provide;
// environment-driven, not a "tested elsewhere" mock.
vi.mock('monaco-editor', () => ({
    editor: {
        create: vi.fn(() => editor),
        defineTheme: vi.fn(),
    },
    KeyCode: { Enter: 3 },
    KeyMod: { CtrlCmd: 2048 },
}));

beforeEach(() => {
    addAction.mockClear();
    editor.dispose.mockClear();
    editor.focus.mockClear();
    editor.getValue.mockClear();
    editor.layout.mockClear();
    onDidChangeModelContent.mockClear();
    languageServerHandle.dispose.mockClear();
    languageServerHandle.notifyContentChanged.mockClear();
    vi.mocked(monaco.editor.create).mockClear();
    vi.mocked(monaco.editor.defineTheme).mockClear();
    vi.mocked(attachLanguageServer)
        .mockReset()
        .mockResolvedValue(languageServerHandle);
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

it('attaches the language server for the current project', () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    expect(attachLanguageServer).toHaveBeenCalledWith(
        monaco,
        'my-project',
        '<?php echo "initial";',
    );
});

it('forwards content changes to the language server once attached', async () => {
    render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await vi.mocked(attachLanguageServer).mock.results[0]?.value;
    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(languageServerHandle.notifyContentChanged).toHaveBeenCalledWith(
        '<?php echo "changed";',
    );
});

it('disposes the language server once attached and the editor unmounts', async () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await vi.mocked(attachLanguageServer).mock.results[0]?.value;
    rendered.unmount();

    expect(languageServerHandle.dispose).toHaveBeenCalledOnce();
});

it('disposes the language server immediately if it resolves after the editor already unmounted', async () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    rendered.unmount();
    await vi.mocked(attachLanguageServer).mock.results[0]?.value;

    expect(languageServerHandle.dispose).toHaveBeenCalledOnce();
});

it('keeps the editor usable when the language server fails to attach', async () => {
    vi.mocked(attachLanguageServer)
        .mockReset()
        .mockRejectedValue(new Error('boom'));

    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";', project: 'my-project' },
    });

    await vi
        .mocked(attachLanguageServer)
        .mock.results[0]?.value.catch(() => undefined);
    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(rendered.emitted().change).toEqual([['<?php echo "changed";']]);
});
