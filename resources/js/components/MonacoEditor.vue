<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';

const props = defineProps<{ initialValue: string }>();
const emit = defineEmits<{ change: [content: string] }>();

const editorElement = useTemplateRef('editorElement');
let editor: MonacoEditorInstance | null = null;

function layout(): void {
    editor?.layout();
}

function content(): string {
    return editor?.getValue() ?? props.initialValue;
}

defineExpose({ content, layout });

onMounted(() => {
    window.require.config({
        paths: {
            vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.56.0/min/vs',
        },
    });
    window.require(['vs/editor/editor.main'], () => {
        window.monaco.editor.defineTheme('github-dark', {
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

        editor = window.monaco.editor.create(editorElement.value, {
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
            emit('change', editor?.getValue() ?? '');
        });
        editor.focus();
    });
});

onBeforeUnmount(() => {
    editor?.dispose();
});
</script>

<template>
    <div ref="editorElement" class="h-full min-w-0 flex-1" />
</template>
