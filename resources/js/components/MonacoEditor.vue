<script setup lang="ts">
import * as monaco from 'monaco-editor';
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue';
import StartLanguageServerController from '@/actions/App/Http/Controllers/StartLanguageServerController';
import StartLaravelLanguageServerController from '@/actions/App/Http/Controllers/StartLaravelLanguageServerController';
import { useTheme } from '@/composables/useTheme';
import { attachLanguageServer } from '@/lib/languageServer';
import type { LanguageServerHandle } from '@/lib/languageServer';
import { createEditorWorker } from '@/lib/monacoEditorWorker';

const props = defineProps<{ initialValue: string; project: string }>();
const emit = defineEmits<{
    change: [content: string];
}>();

const editorElement = useTemplateRef('editorElement');
const { theme } = useTheme();
let editor: monaco.editor.IStandaloneCodeEditor | null = null;
let intelephenseServer: LanguageServerHandle | null = null;
let laravelLspServer: LanguageServerHandle | null = null;
let unmounted = false;

function monacoThemeName(): string {
    return theme.value === 'dark' ? 'tinkerbench-dark' : 'tinkerbench-light';
}

function revealLine(lineNumber: number): void {
    /* v8 ignore next 3 -- guards the exposed method against a call before onMounted assigns `editor`; unreachable once the component is mounted. */
    if (!editor) {
        return;
    }

    editor.revealLineInCenter(lineNumber);
    editor.setPosition({ lineNumber, column: 1 });
    editor.focus();
}

defineExpose({ revealLine });

// Monaco keeps its editing keybindings (cursor and word motion, selection, undo/redo,
// clipboard, line moves, indentation) so the scratch editor behaves like a normal editor.
// Monaco's keybinding service listens on a node inside editorElement, so this capture-phase
// listener only keeps three things away from it: Cmd/Ctrl+F, so neither Monaco's find widget
// nor the browser's page search opens; the function keys, so F1 doesn't open Monaco's command
// palette; and Cmd/Ctrl+Enter, which the window listener owns for running the snippet.
function suppressEditorShortcuts(event: KeyboardEvent): void {
    const chord = event.metaKey || event.ctrlKey;

    if (
        (chord && event.key.toLowerCase() === 'f') ||
        (chord && event.key === 'Enter') ||
        /^F\d{1,2}$/.test(event.key)
    ) {
        event.stopPropagation();
    }
}

onMounted(() => {
    // Monaco resolves language-service workers through this global lookup at the moment
    // it first needs one, so it has to be set before monaco.editor.create() runs below.
    self.MonacoEnvironment = {
        getWorker: createEditorWorker,
    };

    monaco.editor.defineTheme('tinkerbench-dark', {
        base: 'vs-dark',
        inherit: true,
        rules: [
            { token: 'comment', foreground: '8D99A3', fontStyle: 'italic' },
            { token: 'keyword', foreground: '6FB1D2' },
            { token: 'string', foreground: '7CC28A' },
            { token: 'number', foreground: 'B49AE0' },
            { token: 'variable', foreground: 'DCE2E6' },
            { token: 'metatag', foreground: 'F0C030' },
        ],
        colors: {
            'editor.background': '#262B30',
            'editorGutter.background': '#262B30',
            'editor.foreground': '#DCE2E6',
            'editorLineNumber.foreground': '#5C6770',
            'editorLineNumber.activeForeground': '#DCE2E6',
            'editor.lineHighlightBackground': '#2E343A',
            'editorCursor.foreground': '#F0C030',
            'editor.selectionBackground': '#6FB1D233',
            // Brackets stay ink-colored: colored nesting levels would compete with the kind colors.
            'editorBracketHighlight.foreground1': '#DCE2E6',
            'editorBracketHighlight.foreground2': '#DCE2E6',
            'editorBracketHighlight.foreground3': '#DCE2E6',
            'editorBracketHighlight.foreground4': '#DCE2E6',
            'editorBracketHighlight.foreground5': '#DCE2E6',
            'editorBracketHighlight.foreground6': '#DCE2E6',
        },
    });
    monaco.editor.defineTheme('tinkerbench-light', {
        base: 'vs',
        inherit: true,
        rules: [
            { token: 'comment', foreground: '55626C', fontStyle: 'italic' },
            { token: 'keyword', foreground: '2F6E8F' },
            { token: 'string', foreground: '3F7D4E' },
            { token: 'number', foreground: '6D4FA0' },
            { token: 'variable', foreground: '1A2228' },
            { token: 'metatag', foreground: 'A0520A' },
        ],
        colors: {
            'editor.background': '#F7F8F8',
            'editorGutter.background': '#F7F8F8',
            'editor.foreground': '#1A2228',
            'editorLineNumber.foreground': '#9AA4AC',
            'editorLineNumber.activeForeground': '#1A2228',
            'editor.lineHighlightBackground': '#EEF0F1',
            'editorCursor.foreground': '#1A2228',
            'editor.selectionBackground': '#2F6E8F26',
            // Brackets stay ink-colored: colored nesting levels would compete with the kind colors.
            'editorBracketHighlight.foreground1': '#1A2228',
            'editorBracketHighlight.foreground2': '#1A2228',
            'editorBracketHighlight.foreground3': '#1A2228',
            'editorBracketHighlight.foreground4': '#1A2228',
            'editorBracketHighlight.foreground5': '#1A2228',
            'editorBracketHighlight.foreground6': '#1A2228',
        },
    });

    /* v8 ignore next 3 -- the template ref on the always-rendered root <div> is populated by the time onMounted runs. */
    if (!editorElement.value) {
        return;
    }

    const container = editorElement.value;

    const createdEditor = monaco.editor.create(container, {
        value: props.initialValue,
        language: 'php',
        theme: monacoThemeName(),
        automaticLayout: true,
        fontFamily: "'IBM Plex Mono', Menlo, monospace",
        fontSize: 16,
        lineHeight: 28,
        minimap: { enabled: false },
        wordWrap: 'off',
        padding: { top: 16, bottom: 16 },
        fixedOverflowWidgets: true,
        // Editor rendering/interaction options carried over from the user's VS Code
        // settings.json, limited to the ones that matter in an embedded snippet editor.
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
    });

    editor = createdEditor;

    createdEditor.onDidChangeModelContent(() => {
        const value = createdEditor.getValue();

        emit('change', value);
        intelephenseServer?.notifyContentChanged(value);
        laravelLspServer?.notifyContentChanged(value);
    });
    container.addEventListener('keydown', suppressEditorShortcuts, {
        capture: true,
    });
    createdEditor.focus();

    watch(theme, () => monaco.editor.setTheme(monacoThemeName()));

    // Each language server attaches independently: one failing to start (e.g. a machine
    // without laravel-lsp's binary in a broken vendor/ install) doesn't stop the other from
    // attaching, and the editor itself remains fully usable even if both fail.
    attachLanguageServer(
        monaco,
        {
            requestPortUrl: StartLanguageServerController.url(props.project),
            ownerKey: 'intelephense',
        },
        props.initialValue,
        editor.getModel()!,
    )
        .then((handle) => {
            if (unmounted) {
                handle.dispose();

                return;
            }

            intelephenseServer = handle;
        })
        .catch(() => undefined);

    attachLanguageServer(
        monaco,
        {
            requestPortUrl: StartLaravelLanguageServerController.url(
                props.project,
            ),
            ownerKey: 'laravel-lsp',
        },
        props.initialValue,
        editor.getModel()!,
    )
        .then((handle) => {
            if (unmounted) {
                handle.dispose();

                return;
            }

            laravelLspServer = handle;
        })
        .catch(() => undefined);
});

onBeforeUnmount(() => {
    unmounted = true;
    editorElement.value?.removeEventListener(
        'keydown',
        suppressEditorShortcuts,
        { capture: true },
    );
    editor?.dispose();
    intelephenseServer?.dispose();
    laravelLspServer?.dispose();
});
</script>

<template>
    <div ref="editorElement" class="h-full min-w-0 flex-1" />
</template>
