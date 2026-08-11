import { cleanup, render } from '@testing-library/vue';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import MonacoEditor from './MonacoEditor.vue';

const onDidChangeModelContent = vi.fn();
const editor = {
    dispose: vi.fn(),
    focus: vi.fn(),
    getValue: vi.fn(() => '<?php echo "changed";'),
    layout: vi.fn(),
    onDidChangeModelContent,
};

beforeEach(() => {
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
    };
});

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
