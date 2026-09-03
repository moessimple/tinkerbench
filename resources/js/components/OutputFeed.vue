<script setup lang="ts">
import { computed, nextTick, useTemplateRef, watch } from 'vue';
import type { FeedEntry, FeedFilter, FeedSort } from '@/lib/feed';
import { detectOutput, executeScripts, highlightJson } from '@/lib/output';
import type { ExceptionFrame } from '@/types';
import Card from './Card.vue';

const props = withDefaults(
    defineProps<{
        items: FeedEntry[];
        filter?: FeedFilter;
        sort?: FeedSort;
    }>(),
    { filter: 'all', sort: 'recent' },
);

defineEmits<{ navigate: [line: number] }>();

const SEVERE_LOG_LEVELS = ['emergency', 'alert', 'critical', 'error'];

const EMPTY_FACET_LABEL: Record<Exclude<FeedFilter, 'all'>, string> = {
    dump: 'No dumps on this run',
    query: 'No queries on this run',
    log: 'No log messages on this run',
    exception: 'No exceptions on this run',
};

const feedElement = useTemplateRef('feedElement');

function entryDurationMs(entry: FeedEntry): number {
    return entry.kind === 'query' ? entry.duration_ms : 0;
}

const rows = computed(() => {
    const visible = props.items.filter(
        (entry) => props.filter === 'all' || entry.kind === props.filter,
    );

    const ordered =
        props.sort === 'slowest'
            ? [...visible].sort(
                  (a, b) => entryDurationMs(b) - entryDurationMs(a),
              )
            : visible;

    return ordered.map((entry) =>
        entry.kind === 'output'
            ? { entry, output: detectOutput(entry.text) }
            : { entry, output: null },
    );
});

// A lone snippet frame adds nothing the card header (line N) doesn't already show.
function hasTrace(frames: ExceptionFrame[]): boolean {
    return frames.length > 1 || (frames.length === 1 && !frames[0].snippet);
}

function frameLocation(frame: ExceptionFrame): string {
    return frame.snippet
        ? `snippet:${frame.line}`
        : `${frame.file}:${frame.line}`;
}

function frameCountLabel(frames: ExceptionFrame[]): string {
    return `${frames.length} ${frames.length === 1 ? 'stack frame' : 'stack frames'}`;
}

watch(
    () => props.items,
    async () => {
        await nextTick();

        if (feedElement.value) {
            executeScripts(feedElement.value);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div ref="feedElement" class="flex flex-col">
        <p
            v-if="rows.length === 0 && filter !== 'all'"
            class="px-4 py-8 text-center font-mono text-xs text-muted"
        >
            {{ EMPTY_FACET_LABEL[filter] }}
        </p>

        <template v-for="(row, index) in rows" :key="index">
            <Card
                v-if="row.entry.kind === 'dump'"
                label="Dump"
                :line="row.entry.line"
                @navigate="$emit('navigate', $event)"
            >
                <div class="min-w-0" v-html="row.entry.html" />
            </Card>

            <Card
                v-else-if="row.entry.kind === 'query'"
                label="Query"
                :variant="row.entry.slow ? 'danger' : 'default'"
                :line="row.entry.line"
                @navigate="$emit('navigate', $event)"
            >
                <code class="block break-all">{{ row.entry.sql }}</code>
                <template #footer>
                    <span
                        v-if="row.entry.slow"
                        class="rounded bg-danger/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-danger uppercase"
                    >
                        slow
                    </span>
                    <span
                        v-if="row.entry.duplicate"
                        class="rounded bg-danger/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-danger uppercase"
                    >
                        duplicate
                    </span>
                    <span>{{ row.entry.duration_str }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ row.entry.connection }}</span>
                </template>
            </Card>

            <Card
                v-else-if="row.entry.kind === 'log'"
                label="Log"
                :line="row.entry.line"
                :variant="
                    SEVERE_LOG_LEVELS.includes(row.entry.label)
                        ? 'danger'
                        : 'default'
                "
                @navigate="$emit('navigate', $event)"
            >
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span
                        class="text-[10px] tracking-wide uppercase"
                        :class="
                            SEVERE_LOG_LEVELS.includes(row.entry.label)
                                ? 'text-danger'
                                : 'text-muted'
                        "
                    >
                        {{ row.entry.label }}
                    </span>
                    <span class="break-all">{{ row.entry.message }}</span>
                </div>
                <pre
                    v-if="row.entry.context"
                    class="mt-1 overflow-x-auto text-xs text-muted"
                    >{{ row.entry.context }}</pre>
            </Card>

            <Card
                v-else-if="row.entry.kind === 'exception'"
                label="Exception"
                :line="row.entry.line"
                variant="danger"
                @navigate="$emit('navigate', $event)"
            >
                <p>
                    <strong class="text-danger">{{ row.entry.type }}</strong
                    >: {{ row.entry.message }}
                </p>
                <details v-if="hasTrace(row.entry.frames)" class="mt-2 text-xs">
                    <summary
                        class="cursor-pointer tracking-wide text-muted uppercase select-none hover:text-fg"
                    >
                        {{ frameCountLabel(row.entry.frames) }}
                    </summary>
                    <ul class="mt-1.5 flex flex-col gap-0.5">
                        <li
                            v-for="(frame, frameIndex) in row.entry.frames"
                            :key="frameIndex"
                            :data-vendor="frame.vendor"
                            :class="
                                frame.vendor
                                    ? 'text-muted opacity-60'
                                    : 'text-fg'
                            "
                        >
                            {{ frameLocation(frame) }}
                            <span
                                v-if="frame.function && !frame.snippet"
                                class="text-muted"
                            >
                                · {{ frame.function }}
                            </span>
                        </li>
                    </ul>
                </details>
            </Card>

            <Card
                v-else-if="row.entry.kind === 'output'"
                label="Output"
                :line="null"
            >
                <iframe
                    v-if="row.output?.type === 'html'"
                    class="h-64 w-full border-0 bg-white"
                    sandbox="allow-scripts"
                    title="Rendered HTML output"
                    :srcdoc="row.entry.text"
                />
                <pre
                    v-else-if="row.output?.type === 'json'"
                    class="whitespace-pre-wrap"
                    v-html="highlightJson(row.output.pretty)"
                />
                <div
                    v-else-if="row.output?.type === 'dump'"
                    v-html="row.entry.text"
                />
                <pre v-else class="whitespace-pre-wrap">{{
                    row.entry.text
                }}</pre>
            </Card>
        </template>
    </div>
</template>
