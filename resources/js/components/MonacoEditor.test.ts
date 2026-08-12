import { cleanup, render } from '@testing-library/vue';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import MonacoEditor from './MonacoEditor.vue';

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

beforeEach(() => {
    addAction.mockClear();
    editor.dispose.mockClear();
    editor.focus.mockClear();
    editor.getValue.mockClear();
    editor.layout.mockClear();
    onDidChangeModelContent.mockClear();

    window.require = Object.assign(
        (_modules: string[], callback: () => void) => callback(),
        {
            config: vi.fn(),
        },
    );
    window.monaco = {
        editor: {
            create: vi.fn(() => editor),
            defineTheme: vi.fn(),
        },
        KeyCode: { Enter: 3, KeyP: 46 },
        KeyMod: { CtrlCmd: 2048 },
    };
});

function actionRun(id: string): () => void {
    const action = addAction.mock.calls.find((call) => call[0].id === id)?.[0];

    if (!action) {
        throw new Error(`No action registered with id "${id}"`);
    }

    return action.run;
}

afterEach(cleanup);

it('creates the editor with PHP syntax highlighting and the github-dark theme', () => {
    render(MonacoEditor, { props: { initialValue: '<?php echo "initial";' } });

    expect(window.monaco.editor.defineTheme).toHaveBeenCalledWith(
        'github-dark',
        expect.any(Object),
    );
    expect(window.monaco.editor.create).toHaveBeenCalledWith(
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
        props: { initialValue: '<?php echo "initial";' },
    });

    onDidChangeModelContent.mock.calls[0]?.[0]();

    expect(rendered.emitted().change).toEqual([['<?php echo "changed";']]);
});

it('disposes the editor when unmounted', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";' },
    });

    rendered.unmount();

    expect(editor.dispose).toHaveBeenCalledOnce();
});

it('emits run when the Ctrl/Cmd+Enter action runs', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";' },
    });

    actionRun('tinkerbench.run')();

    expect(rendered.emitted().run).toHaveLength(1);
    expect(addAction).toHaveBeenCalledWith(
        expect.objectContaining({
            keybindings: [
                window.monaco.KeyMod.CtrlCmd | window.monaco.KeyCode.Enter,
            ],
        }),
    );
});

it('emits browseSnippets when the Ctrl/Cmd+P action runs', () => {
    const rendered = render(MonacoEditor, {
        props: { initialValue: '<?php echo "initial";' },
    });

    actionRun('tinkerbench.browseSnippets')();

    expect(rendered.emitted().browseSnippets).toHaveLength(1);
    expect(addAction).toHaveBeenCalledWith(
        expect.objectContaining({
            keybindings: [
                window.monaco.KeyMod.CtrlCmd | window.monaco.KeyCode.KeyP,
            ],
        }),
    );
});
