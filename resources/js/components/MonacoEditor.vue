<script setup lang="ts">
import * as monaco from 'monaco-editor';
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { attachLanguageServer } from '@/lib/languageServer';
import type { LanguageServerHandle } from '@/lib/languageServer';
import { createEditorWorker } from '@/lib/monacoEditorWorker';

const props = defineProps<{ initialValue: string; project: string }>();
const emit = defineEmits<{
    change: [content: string];
    run: [];
}>();

const editorElement = useTemplateRef('editorElement');
const { theme } = useTheme();
let editor: monaco.editor.IStandaloneCodeEditor | null = null;
let languageServer: LanguageServerHandle | null = null;
let unmounted = false;

function monacoThemeName(): string {
    return theme.value === 'dark' ? 'github-dark' : 'github-light';
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

    editor = monaco.editor.create(editorElement.value, {
        value: props.initialValue,
        language: 'php',
        theme: monacoThemeName(),
        automaticLayout: true,
        fontFamily: "'Fira Code', Menlo, monospace",
        fontLigatures: true,
        fontSize: 16,
        lineHeight: 26,
        minimap: { enabled: false },
        wordWrap: 'on',
        padding: { top: 16, bottom: 16 },
    });

    editor.onDidChangeModelContent(() => {
        const value = editor?.getValue() ?? '';

        emit('change', value);
        languageServer?.notifyContentChanged(value);
    });
    editor.addAction({
        id: 'tinkerbench.run',
        label: 'Tinkerbench: Run Snippet',
        keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter],
        run: () => emit('run'),
    });
    editor.focus();

    watch(theme, () => monaco.editor.setTheme(monacoThemeName()));

    attachLanguageServer(
        monaco,
        props.project,
        props.initialValue,
        editor.getModel()!,
    )
        .then((handle) => {
            if (unmounted) {
                handle.dispose();

                return;
            }

            languageServer = handle;
        })
        // The language server is optional: PHP autocompletion/hover/signature help stay off,
        // but the editor itself remains fully usable without it.
        .catch(() => undefined);
});

onBeforeUnmount(() => {
    unmounted = true;
    editor?.dispose();
    languageServer?.dispose();
});
</script>

<template>
    <div ref="editorElement" class="h-full min-w-0 flex-1" />
</template>
