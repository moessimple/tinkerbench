<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import RunSnippetController from '@/actions/Application/Snippets/Controllers/RunSnippetController';
import UpdateSnippetContentController from '@/actions/Application/Snippets/Controllers/UpdateSnippetContentController';
import CommandPalette from '@/components/CommandPalette.vue';
import MonacoEditor from '@/components/MonacoEditor.vue';
import { xsrfHeader } from '@/lib/csrf';
import { shortcuts } from '@/lib/shortcuts';

const props = defineProps<{
    content: string;
    currentProject: string;
    laravelVersion: string;
    phpVersion: string;
    snippetName: string;
}>();

const runShortcut = shortcuts.find((shortcut) => shortcut.id === 'run')?.keys;
const pageTitle = computed(
    () => `${props.currentProject} / ${props.snippetName}`,
);

const output = ref('');
const errorMessage = ref('');
const outputMode = ref<'raw' | 'rendered'>('raw');
const isMaximized = ref(false);

const http = useHttp<{ code: string }, { output: string }>({
    code: props.content,
});

let saveTimer: ReturnType<typeof window.setTimeout> | undefined;
let pendingSave = Promise.resolve();

function persistSnippet(content: string): Promise<void> {
    return fetch(
        UpdateSnippetContentController.url([
            props.currentProject,
            props.snippetName,
        ]),
        {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', ...xsrfHeader() },
            body: JSON.stringify({ content }),
        },
    ).then(() => undefined);
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

    http.post(RunSnippetController.url(props.currentProject), {
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

function clearOutput(): void {
    output.value = '';
    errorMessage.value = '';
}

function toggleOutputMode(): void {
    outputMode.value = outputMode.value === 'raw' ? 'rendered' : 'raw';
}

function toggleMaximize(): void {
    isMaximized.value = !isMaximized.value;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex min-h-screen flex-col bg-canvas text-fg">
        <div
            v-if="!isMaximized"
            class="mx-auto shrink-0 px-4 pt-10 pb-6 text-center"
        >
            <div
                class="mb-4 flex items-center justify-center gap-2 font-mono text-xs font-semibold tracking-widest text-muted uppercase"
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
            <h1 class="text-3xl font-bold text-fg">
                {{ currentProject }}
                <span class="text-muted">/</span>
                {{ snippetName }}
            </h1>
            <p class="mt-2 font-mono text-xs text-muted">
                PHP {{ phpVersion }} · Laravel {{ laravelVersion }}
            </p>
        </div>

        <div
            class="flex w-full flex-1 flex-col gap-4 px-6"
            :class="isMaximized ? 'fixed inset-0 z-30 bg-canvas py-4' : 'mb-6'"
        >
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-line bg-surface min-[900px]:flex-row"
            >
                <div
                    class="flex min-h-0 min-w-0 flex-3 border-b border-line min-[900px]:border-r min-[900px]:border-b-0"
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
                        <button
                            type="button"
                            title="Clear output"
                            aria-label="Clear output"
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="clearOutput"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4 1.75V3H1.75a.75.75 0 0 0 0 1.5h.6l.63 9.44A2 2 0 0 0 4.98 16h6.04a2 2 0 0 0 1.99-1.86l.63-9.44h.6a.75.75 0 0 0 0-1.5H12V1.75A1.75 1.75 0 0 0 10.25 0h-4.5A1.75 1.75 0 0 0 4 1.75Zm1.5 0a.25.25 0 0 1 .25-.25h4.5a.25.25 0 0 1 .25.25V3h-5V1.75ZM4.5 4.5h7l-.62 9.32a.5.5 0 0 1-.5.43H5.62a.5.5 0 0 1-.5-.43L4.5 4.5Z"
                                />
                            </svg>
                        </button>
                        <button
                            type="button"
                            :title="
                                outputMode === 'raw'
                                    ? 'Show rendered output'
                                    : 'Show raw output'
                            "
                            :aria-label="
                                outputMode === 'raw'
                                    ? 'Show rendered output'
                                    : 'Show raw output'
                            "
                            :aria-pressed="outputMode === 'rendered'"
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="toggleOutputMode"
                        >
                            <svg
                                v-if="outputMode === 'raw'"
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4.72 3.22a.75.75 0 0 1 0 1.06L2.06 8l2.66 2.72a.75.75 0 1 1-1.06 1.06L.47 8.53a.75.75 0 0 1 0-1.06l3.19-3.25a.75.75 0 0 1 1.06 0Zm6.56 0a.75.75 0 0 1 1.06 0l3.19 3.25a.75.75 0 0 1 0 1.06l-3.19 3.25a.75.75 0 1 1-1.06-1.06L13.94 8l-2.66-2.72a.75.75 0 0 1 0-1.06Z"
                                />
                            </svg>
                            <svg
                                v-else
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M8 3C4.5 3 1.7 5.4 1 8c.7 2.6 3.5 5 7 5s6.3-2.4 7-5c-.7-2.6-3.5-5-7-5Zm0 8.2A3.2 3.2 0 1 1 8 4.8a3.2 3.2 0 0 1 0 6.4Z"
                                />
                                <circle cx="8" cy="8" r="1.6" />
                            </svg>
                        </button>
                        <CommandPalette
                            :current-project="currentProject"
                            :current-snippet="snippetName"
                        />
                        <button
                            type="button"
                            :title="isMaximized ? 'Minimize' : 'Maximize'"
                            :aria-label="isMaximized ? 'Minimize' : 'Maximize'"
                            :aria-pressed="isMaximized"
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="toggleMaximize"
                        >
                            <svg
                                v-if="!isMaximized"
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4"
                                />
                            </svg>
                            <svg
                                v-else
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M6 2v4H2M10 2v4h4M6 14v-4H2M10 14v-4h4"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 min-w-0 flex-1">
                        <MonacoEditor
                            :initial-value="http.code"
                            @change="onEditorChange"
                            @run="run"
                        />
                    </div>
                </div>

                <div class="flex min-h-0 min-w-0 flex-2 flex-col">
                    <div
                        class="flex shrink-0 items-center border-b border-line px-4"
                    >
                        <span
                            class="border-b-2 border-accent px-0 py-3 font-mono text-xs font-semibold tracking-widest text-fg uppercase"
                        >
                            Output
                        </span>
                    </div>
                    <pre
                        v-if="outputMode === 'raw'"
                        class="min-h-0 flex-1 overflow-auto p-4 font-mono text-sm whitespace-pre-wrap"
                        >{{ output }}</pre>
                    <div
                        v-else
                        class="min-h-0 flex-1 overflow-auto p-4 font-mono text-sm"
                        v-html="output"
                    />
                </div>
            </div>

            <p v-if="errorMessage" class="font-mono text-sm text-red-400">
                {{ errorMessage }}
            </p>
        </div>
    </div>
</template>
