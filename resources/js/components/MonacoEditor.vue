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
    return theme.value === 'dark' ? 'github-dark' : 'github-light';
}

function revealLine(lineNumber: number): void {
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

    monaco.editor.defineTheme('github-dark', {
        base: 'vs-dark',
        inherit: true,
        rules: [],
        colors: {
            'editor.background': '#161B22',
            'editorGutter.background': '#161B22',
            'editor.foreground': '#E6EDF3',
            'editorLineNumber.foreground': '#7D8590',
            'editorLineNumber.activeForeground': '#E6EDF3',
            'editor.lineHighlightBackground': '#242E3A',
            'editorCursor.foreground': '#4493F8',
            'editor.selectionBackground': '#4493F826',
        },
    });
    monaco.editor.defineTheme('github-light', {
        base: 'vs',
        inherit: true,
        rules: [],
        colors: {
            'editor.background': '#F6F8FA',
            'editorGutter.background': '#F6F8FA',
            'editor.foreground': '#1F2328',
            'editorLineNumber.foreground': '#59636E',
            'editorLineNumber.activeForeground': '#1F2328',
            'editor.lineHighlightBackground': '#EAEEF2',
            'editorCursor.foreground': '#0969DA',
            'editor.selectionBackground': '#0969DA26',
        },
    });

    if (!editorElement.value) {
        return;
    }

    const container = editorElement.value;

    editor = monaco.editor.create(container, {
        value: props.initialValue,
        language: 'php',
        theme: monacoThemeName(),
        automaticLayout: true,
        fontFamily: "'Fira Code', Menlo, monospace",
        fontLigatures: true,
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

    editor.onDidChangeModelContent(() => {
        const value = editor?.getValue() ?? '';

        emit('change', value);
        intelephenseServer?.notifyContentChanged(value);
        laravelLspServer?.notifyContentChanged(value);
    });
    container.addEventListener('keydown', suppressEditorShortcuts, {
        capture: true,
    });
    editor.focus();

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
