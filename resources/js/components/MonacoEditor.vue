<script setup lang="ts">
import * as monaco from 'monaco-editor';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { attachLanguageServer } from '@/lib/languageServer';
import type { LanguageServerHandle } from '@/lib/languageServer';
import { createEditorWorker } from '@/lib/monacoEditorWorker';

const props = defineProps<{ initialValue: string; project: string }>();
const emit = defineEmits<{
    change: [content: string];
    run: [];
}>();

const editorElement = useTemplateRef('editorElement');
let editor: monaco.editor.IStandaloneCodeEditor | null = null;
let languageServer: LanguageServerHandle | null = null;
let unmounted = false;

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
            'editor.background': '#1C2530',
            'editorGutter.background': '#1C2530',
            'editor.foreground': '#F0F6FC',
            'editorLineNumber.foreground': '#9198A1',
            'editorLineNumber.activeForeground': '#F0F6FC',
            'editor.lineHighlightBackground': '#242E3A',
            'editorCursor.foreground': '#58A6FF',
            'editor.selectionBackground': '#58A6FF26',
        },
    });

    if (!editorElement.value) {
        return;
    }

    editor = monaco.editor.create(editorElement.value, {
        value: props.initialValue,
        language: 'php',
        theme: 'github-dark',
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

    attachLanguageServer(monaco, props.project, props.initialValue)
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
