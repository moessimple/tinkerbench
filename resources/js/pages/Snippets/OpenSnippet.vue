<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import RunSnippetController from '@/actions/App/Http/Controllers/RunSnippetController';
import UpdateSnippetContentController from '@/actions/App/Http/Controllers/UpdateSnippetContentController';
import CommandPalette from '@/components/CommandPalette.vue';
import MonacoEditor from '@/components/MonacoEditor.vue';
import OutputFeed from '@/components/OutputFeed.vue';
import { useTheme } from '@/composables/useTheme';
import { xsrfHeader } from '@/lib/csrf';
import { buildFeed } from '@/lib/feed';
import type { FeedEntry, FeedFilter, FeedSort } from '@/lib/feed';
import { shortcuts } from '@/lib/shortcuts';
import type { FeedItem, SnippetDebugPayload } from '@/types';

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

const rawOutput = ref('');
const debug = ref<SnippetDebugPayload | null>(null);
const errorMessage = ref('');
const isMaximized = ref(false);
const activeFilter = ref<FeedFilter>('all');
const querySort = ref<FeedSort>('recent');
const editorRef = useTemplateRef<{ revealLine: (line: number) => void }>(
    'editor',
);

const { theme, toggleTheme } = useTheme();

const feedFilters: { label: string; value: FeedFilter }[] = [
    { label: 'All', value: 'all' },
    { label: 'Dumps', value: 'dump' },
    { label: 'Queries', value: 'query' },
    { label: 'Logs', value: 'log' },
    { label: 'Exceptions', value: 'exception' },
];

const querySorts: { label: string; value: FeedSort }[] = [
    { label: 'Recent', value: 'recent' },
    { label: 'Slowest', value: 'slowest' },
];

const kindCounts = computed<Record<FeedItem['kind'], number>>(() => {
    const counts: Record<FeedItem['kind'], number> = {
        dump: 0,
        query: 0,
        log: 0,
        exception: 0,
    };

    for (const item of debug.value?.items ?? []) {
        counts[item.kind] += 1;
    }

    return counts;
});

function filterCount(filter: FeedFilter): number {
    return filter === 'all'
        ? (debug.value?.items.length ?? 0)
        : kindCounts.value[filter];
}

const feedEntries = computed<FeedEntry[]>(() =>
    buildFeed(
        debug.value ?? { items: [], duration_str: '', peak_memory_str: '' },
        rawOutput.value,
    ),
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

function run(): void {
    errorMessage.value = '';
    rawOutput.value = '';
    debug.value = null;

    http.post(RunSnippetController.url(props.currentProject), {
        onSuccess: (data) => {
            rawOutput.value = data.output;
            debug.value = data.debug;
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
    rawOutput.value = '';
    debug.value = null;
    errorMessage.value = '';
    activeFilter.value = 'all';
    querySort.value = 'recent';
}

function revealEditorLine(line: number): void {
    editorRef.value?.revealLine(line);
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
                        <button
                            type="button"
                            :title="
                                theme === 'dark'
                                    ? 'Switch to light theme'
                                    : 'Switch to dark theme'
                            "
                            :aria-label="
                                theme === 'dark'
                                    ? 'Switch to light theme'
                                    : 'Switch to dark theme'
                            "
                            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
                            @click="toggleTheme"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <template v-if="theme === 'dark'">
                                    <circle cx="8" cy="8" r="3" />
                                    <path
                                        d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.41 1.41M11.54 11.54l1.41 1.41M3.05 12.95l1.41-1.41M11.54 4.46l1.41-1.41"
                                    />
                                </template>
                                <path
                                    v-else
                                    d="M13.5 9.5A6 6 0 1 1 6.5 2.5a5 5 0 0 0 7 7Z"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 min-w-0 flex-1">
                        <MonacoEditor
                            ref="editor"
                            :initial-value="http.code"
                            :project="currentProject"
                            @change="onEditorChange"
                            @run="run"
                        />
                    </div>
                </div>

                <div class="flex min-h-0 min-w-0 flex-2 flex-col">
                    <div
                        class="flex shrink-0 flex-wrap items-center gap-x-2 gap-y-1 border-b border-line px-4 py-2 font-mono text-xs text-muted"
                    >
                        <span>
                            PHP {{ phpVersion }} · Laravel {{ laravelVersion }}
                        </span>
                        <template v-if="debug">
                            <span aria-hidden="true">·</span>
                            <span>{{ debug.duration_str }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ debug.peak_memory_str }}</span>
                        </template>
                    </div>
                    <div
                        v-if="debug"
                        role="tablist"
                        aria-label="Filter output by kind"
                        class="flex shrink-0 gap-0.5 overflow-x-auto border-b border-line px-3 py-1.5 font-mono text-[11px]"
                    >
                        <button
                            v-for="filter in feedFilters"
                            :key="filter.value"
                            type="button"
                            role="tab"
                            :aria-selected="activeFilter === filter.value"
                            class="flex shrink-0 items-baseline gap-1 rounded px-1.5 py-0.5 tracking-wide whitespace-nowrap uppercase"
                            :class="
                                activeFilter === filter.value
                                    ? 'bg-accent/10 text-accent'
                                    : 'text-muted hover:bg-line/40 hover:text-fg'
                            "
                            @click="activeFilter = filter.value"
                        >
                            {{ filter.label }}
                            <span
                                class="tabular-nums"
                                :class="
                                    activeFilter === filter.value
                                        ? 'text-accent/70'
                                        : 'text-muted/70'
                                "
                            >
                                {{ filterCount(filter.value) }}
                            </span>
                        </button>
                    </div>
                    <div
                        v-if="debug && activeFilter === 'query'"
                        class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-1.5 font-mono text-[11px] text-muted"
                    >
                        <span>{{ kindCounts.query }} statements</span>
                        <div class="ml-auto flex items-center gap-1">
                            <span class="tracking-wide uppercase">Sort</span>
                            <button
                                v-for="option in querySorts"
                                :key="option.value"
                                type="button"
                                :aria-pressed="querySort === option.value"
                                class="rounded px-1.5 py-0.5 tracking-wide uppercase"
                                :class="
                                    querySort === option.value
                                        ? 'bg-accent/10 text-accent'
                                        : 'hover:bg-line/40 hover:text-fg'
                                "
                                @click="querySort = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <div
                        role="region"
                        aria-label="Snippet output"
                        class="min-h-0 flex-1 overflow-auto"
                    >
                        <OutputFeed
                            :items="feedEntries"
                            :filter="activeFilter"
                            :sort="
                                activeFilter === 'query' ? querySort : 'recent'
                            "
                            @navigate="revealEditorLine"
                        />
                    </div>
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
