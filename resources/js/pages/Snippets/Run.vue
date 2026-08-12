<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import RunSnippetController from '@/actions/Application/Snippets/Controllers/RunSnippetController';
import UpdateSnippetContentController from '@/actions/Application/Snippets/Controllers/UpdateSnippetContentController';
import MonacoEditor from '@/components/MonacoEditor.vue';
import SnippetList from '@/components/SnippetList.vue';
import { xsrfHeader } from '@/lib/csrf';
import { shortcuts } from '@/lib/shortcuts';

const props = defineProps<{
    content: string;
    laravelVersion: string;
    phpVersion: string;
    snippetName: string;
}>();

const runShortcut = shortcuts.find(
    (shortcut) => shortcut.description === 'Run snippet',
)?.keys;

const output = ref('');
const errorMessage = ref('');

const http = useHttp<{ code: string }, { output: string }>({
    code: props.content,
});

let saveTimer: ReturnType<typeof window.setTimeout> | undefined;
let pendingSave = Promise.resolve();

function persistSnippet(content: string): Promise<void> {
    return fetch(UpdateSnippetContentController.url(props.snippetName), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...xsrfHeader() },
        body: JSON.stringify({ content }),
    }).then(() => undefined);
}

function queueSnippetSave(content: string): Promise<void> {
    pendingSave = pendingSave.then(() => persistSnippet(content));

    return pendingSave;
}

function onEditorChange(content: string): void {
    http.code = content;
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => void queueSnippetSave(content), 500);
}

onBeforeUnmount(() => window.clearTimeout(saveTimer));

function run(): void {
    errorMessage.value = '';

    http.post(RunSnippetController.url(), {
        onSuccess: (data) => {
            output.value = data.output;
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors).join(' ');
        },
        onHttpException: (response) => {
            errorMessage.value = `Request failed (${response.status}).`;
        },
    });
}
</script>

<template>
    <Head title="Snippets" />

    <div class="flex min-h-screen flex-col bg-canvas text-fg">
        <div class="mx-auto shrink-0 px-4 pt-10 pb-8 text-center">
            <div
                class="mb-6 flex items-center justify-center gap-2 font-mono text-xs font-semibold tracking-widest text-muted uppercase"
            >
                <svg
                    viewBox="0 0 16 16"
                    width="14"
                    height="14"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="text-accent"
                    aria-hidden="true"
                >
                    <path d="M5.5 5.2c0-1.8 1.1-3.2 2.5-3.2s2.5 1.4 2.5 3.2" />
                    <path
                        d="M3.8 5.2h8.4l-1.3 7.4a1 1 0 0 1-1 .8H6.1a1 1 0 0 1-1-.8L3.8 5.2Z"
                    />
                </svg>
                tinkerbench
            </div>
            <p class="font-mono text-xs text-muted">
                PHP {{ phpVersion }} · Laravel {{ laravelVersion }}
            </p>
        </div>

        <div
            class="mx-auto mb-6 flex w-full max-w-358 flex-1 flex-col gap-4 px-4"
        >
            <div
                class="flex flex-col overflow-hidden rounded-md border border-line bg-surface min-[900px]:flex-row"
            >
                <div
                    class="flex min-w-0 flex-3 border-b border-line min-[900px]:border-r min-[900px]:border-b-0"
                >
                    <div
                        class="flex w-12 shrink-0 flex-col items-center gap-1 border-r border-line py-3"
                    >
                        <button
                            type="button"
                            :title="
                                http.processing
                                    ? 'Running…'
                                    : `Run snippet (${runShortcut})`
                            "
                            :aria-label="
                                http.processing ? 'Running…' : 'Run snippet'
                            "
                            :disabled="http.processing"
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg disabled:opacity-50"
                            @click="run"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 2.5a.5.5 0 0 1 .77-.42l9 5.5a.5.5 0 0 1 0 .84l-9 5.5A.5.5 0 0 1 3 13.5v-11Z"
                                />
                            </svg>
                        </button>
                        <SnippetList :current-snippet="snippetName" />
                    </div>
                    <div class="h-96 min-w-0 flex-1">
                        <MonacoEditor
                            :initial-value="http.code"
                            @change="onEditorChange"
                            @run="run"
                        />
                    </div>
                </div>

                <div class="flex min-h-0 min-w-0 flex-2 flex-col">
                    <pre
                        class="h-96 flex-1 overflow-auto p-4 font-mono text-sm whitespace-pre-wrap"
                        >{{ output }}</pre>
                </div>
            </div>

            <p v-if="errorMessage" class="font-mono text-sm text-red-400">
                {{ errorMessage }}
            </p>
        </div>
    </div>
</template>
