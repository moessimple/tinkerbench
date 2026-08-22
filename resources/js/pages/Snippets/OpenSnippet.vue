<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from 'vue';
import RunSnippetController from '@/actions/App/Http/Controllers/RunSnippetController';
import UpdateSnippetContentController from '@/actions/App/Http/Controllers/UpdateSnippetContentController';
import CommandPalette from '@/components/CommandPalette.vue';
import DebugPanel from '@/components/DebugPanel.vue';
import MonacoEditor from '@/components/MonacoEditor.vue';
import { xsrfHeader } from '@/lib/csrf';
import { detectOutput, executeScripts, highlightJson } from '@/lib/output';
import type { OutputResult } from '@/lib/output';
import { shortcuts } from '@/lib/shortcuts';
import type { SnippetDebugPayload } from '@/types';

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

const lastResult = ref<OutputResult | null>(null);
const debug = ref<SnippetDebugPayload | null>(null);
const activeTab = ref<'debug' | 'output'>('output');
const errorMessage = ref('');
const outputMode = ref<'raw' | 'rendered'>('raw');
const isMaximized = ref(false);
const outputEl = useTemplateRef('output');

const outputText = computed(() => lastResult.value?.raw ?? '');
const renderedJson = computed(() =>
    lastResult.value?.type === 'json'
        ? highlightJson(lastResult.value.pretty)
        : '',
);
const showsFrame = computed(
    () => outputMode.value === 'rendered' && lastResult.value?.type === 'html',
);
const showsMarkup = computed(
    () =>
        outputMode.value === 'rendered' &&
        (lastResult.value?.type === 'dump' ||
            lastResult.value?.type === 'json'),
);

const http = useHttp<
    { code: string },
    { debug: SnippetDebugPayload | null; output: string }
>({
    code: props.content,
});

const saveError = ref('');

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
    ).then((response) => {
        if (!response.ok) {
            throw new Error(`Unable to save changes (${response.status}).`);
        }
    });
}

function queueSnippetSave(content: string): void {
    // Recover from a prior rejection here, not by handling it where pendingSave is read,
    // so one failed save can't permanently block every save queued after it.
    pendingSave = pendingSave
        .catch(() => undefined)
        .then(() => persistSnippet(content));

    pendingSave.then(
        () => (saveError.value = ''),
        (error: unknown) =>
            (saveError.value =
                error instanceof Error
                    ? error.message
                    : 'Unable to save changes.'),
    );
}

function onEditorChange(content: string): void {
    http.code = content;
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => queueSnippetSave(content), 500);
}

function flushSave(): void {
    window.clearTimeout(saveTimer);
    saveTimer = undefined;
    queueSnippetSave(http.code);
}

onBeforeUnmount(() => {
    if (saveTimer !== undefined) {
        flushSave();
    }
});

function onGlobalKeydown(event: KeyboardEvent): void {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        flushSave();
    }
}

onMounted(() =>
    window.addEventListener('keydown', onGlobalKeydown, { capture: true }),
);
onBeforeUnmount(() =>
    window.removeEventListener('keydown', onGlobalKeydown, { capture: true }),
);

async function executeDumpScripts(): Promise<void> {
    await nextTick();

    if (outputEl.value) {
        executeScripts(outputEl.value);
    }
}

function run(): void {
    errorMessage.value = '';
    debug.value = null;
    activeTab.value = 'output';

    http.post(RunSnippetController.url(props.currentProject), {
        onSuccess: async (data) => {
            lastResult.value = detectOutput(data.output);
            debug.value = data.debug;

            if (
                outputMode.value === 'rendered' &&
                lastResult.value.type === 'dump'
            ) {
                await executeDumpScripts();
            }
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
    lastResult.value = null;
    debug.value = null;
    activeTab.value = 'output';
    errorMessage.value = '';
}

async function toggleOutputMode(): Promise<void> {
    outputMode.value = outputMode.value === 'raw' ? 'rendered' : 'raw';

    if (outputMode.value === 'rendered' && lastResult.value?.type === 'dump') {
        await executeDumpScripts();
    }
}

function toggleMaximize(): void {
    isMaximized.value = !isMaximized.value;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex h-full flex-col bg-canvas text-fg">
        <div
            v-if="!isMaximized"
            class="mx-auto hidden max-w-358 shrink-0 px-4 pt-10 pb-8 text-center min-[900px]:block"
        >
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
            <h1 class="font-mono text-2xl font-semibold text-fg">
                {{ currentProject }}
                <span class="text-muted">/</span>
                {{ snippetName }}
            </h1>
            <p class="mt-2 font-mono text-xs text-muted">
                PHP {{ phpVersion }} · Laravel {{ laravelVersion }}
            </p>
        </div>

        <div
            class="flex min-h-0 w-full flex-1"
            :class="
                isMaximized
                    ? 'fixed inset-0 z-30'
                    : 'mx-auto mb-6 max-w-358 rounded-md px-4'
            "
        >
            <div
                class="flex w-full flex-col overflow-hidden bg-surface min-[900px]:flex-row"
                :class="{ 'rounded-md border border-line': !isMaximized }"
            >
                <div
                    class="flex min-h-0 min-w-0 flex-3 border-b border-line min-[900px]:border-b-0"
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
                        <CommandPalette
                            :current-project="currentProject"
                            :current-snippet="snippetName"
                        />
                        <button
                            type="button"
                            :title="
                                isMaximized
                                    ? 'Exit fullscreen'
                                    : 'Toggle fullscreen'
                            "
                            :aria-label="
                                isMaximized
                                    ? 'Exit fullscreen'
                                    : 'Toggle fullscreen'
                            "
                            :aria-pressed="isMaximized"
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="toggleMaximize"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                aria-hidden="true"
                            >
                                <path
                                    v-if="isMaximized"
                                    d="M6 2v4H2M10 14v-4h4M14 6h-4V2M2 10h4v4"
                                />
                                <path
                                    v-else
                                    d="M2 6V2h4M14 10v4h-4M10 2h4v4M2 10v4h4"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 min-w-0 flex-1">
                        <MonacoEditor
                            :initial-value="http.code"
                            :project="currentProject"
                            @change="onEditorChange"
                            @run="run"
                        />
                    </div>
                </div>

                <div class="flex min-h-0 min-w-0 flex-2 flex-col">
                    <div
                        role="tablist"
                        class="flex shrink-0 border-b border-line"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === 'output'"
                            class="px-3 py-2 font-mono text-xs font-semibold tracking-widest uppercase"
                            :class="
                                activeTab === 'output'
                                    ? 'border-b-2 border-accent text-fg'
                                    : 'text-muted hover:text-fg'
                            "
                            @click="activeTab = 'output'"
                        >
                            Output
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === 'debug'"
                            class="px-3 py-2 font-mono text-xs font-semibold tracking-widest uppercase"
                            :class="
                                activeTab === 'debug'
                                    ? 'border-b-2 border-accent text-fg'
                                    : 'text-muted hover:text-fg'
                            "
                            @click="activeTab = 'debug'"
                        >
                            Debug
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
                            class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="toggleOutputMode"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                aria-hidden="true"
                            >
                                <template v-if="outputMode === 'raw'">
                                    <path
                                        d="M1 8s2.7-4.5 7-4.4S15 8 15 8s-2.7 4.5-7 4.4S1 8 1 8Z"
                                    />
                                    <circle cx="8" cy="8" r="1.6" />
                                </template>
                                <path v-else d="M5 4 2 8l3 4M11 4l3 4-3 4" />
                            </svg>
                        </button>
                    </div>
                    <div
                        v-show="activeTab === 'output' && !showsFrame"
                        ref="output"
                        role="status"
                        aria-label="Snippet output"
                        aria-live="polite"
                        class="min-h-0 flex-1 overflow-auto p-4 font-mono text-base leading-6.5 whitespace-pre-wrap [font-variant-ligatures:none]"
                    >
                        <span
                            v-if="showsMarkup"
                            v-html="
                                lastResult?.type === 'json'
                                    ? renderedJson
                                    : lastResult?.raw
                            "
                        />
                        <template v-else>{{ outputText }}</template>
                    </div>
                    <iframe
                        v-show="activeTab === 'output' && showsFrame"
                        class="min-h-0 flex-1 border-0 bg-white"
                        sandbox="allow-scripts"
                        title="Rendered HTML output"
                        :srcdoc="
                            lastResult?.type === 'html' ? lastResult.raw : ''
                        "
                    />
                    <DebugPanel
                        v-if="activeTab === 'debug'"
                        :debug="debug ?? {}"
                    />
                </div>
            </div>
        </div>

        <p
            v-if="errorMessage"
            role="alert"
            class="fixed right-4 bottom-4 rounded-md border border-red-500/40 bg-surface px-4 py-3 font-mono text-sm text-red-400 shadow-2xl"
        >
            {{ errorMessage }}
        </p>
        <p
            v-if="saveError"
            role="alert"
            class="fixed top-4 right-4 rounded-md border border-red-500/40 bg-surface px-4 py-3 font-mono text-sm text-red-400 shadow-2xl"
        >
            {{ saveError }}
        </p>
    </div>
</template>
