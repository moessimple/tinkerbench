<script setup lang="ts">
import { computed, nextTick, useTemplateRef, watch } from 'vue';
import type { FeedEntry } from '@/lib/feed';
import { detectOutput, executeScripts, highlightJson } from '@/lib/output';
import Card from './Card.vue';

const props = withDefaults(
    defineProps<{ items: FeedEntry[]; hideQueries?: boolean }>(),
    { hideQueries: false },
);

defineEmits<{ navigate: [line: number] }>();

const SEVERE_LOG_LEVELS = ['emergency', 'alert', 'critical', 'error'];

const feedElement = useTemplateRef('feedElement');

const rows = computed(() =>
    props.items
        .filter((entry) => !(props.hideQueries && entry.kind === 'query'))
        .map((entry) =>
            entry.kind === 'output'
                ? { entry, output: detectOutput(entry.text) }
                : { entry, output: null },
        ),
);

function frameCode(snippet: { code: string; line: number }[]): string {
    return snippet.map((row) => row.code).join('\n');
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
                :line="row.entry.line"
                @navigate="$emit('navigate', $event)"
            >
                <code class="block break-all">{{ row.entry.sql }}</code>
                <template #footer>
                    <span v-if="row.entry.slow" class="mr-2 text-red-400">
                        slow
                    </span>
                    <span v-if="row.entry.duplicate" class="mr-2 text-red-400">
                        duplicate
                    </span>
                    {{ row.entry.duration_str }} · {{ row.entry.connection }}
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
                <span class="mr-2 text-xs text-muted uppercase">
                    {{ row.entry.label }}
                </span>
                <span class="break-all">{{ row.entry.message }}</span>
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
                    <strong>{{ row.entry.type }}</strong
                    >: {{ row.entry.message }}
                </p>
                <ul class="mt-2 flex flex-col gap-1 text-xs">
                    <li
                        v-for="(frame, frameIndex) in row.entry.frames"
                        :key="frameIndex"
                        :data-vendor="frame.vendor"
                        :class="
                            frame.vendor ? 'text-muted opacity-60' : 'text-fg'
                        "
                    >
                        {{ frame.file }}:{{ frame.line }}
                        <span v-if="frame.function" class="text-muted">
                            — {{ frame.function }}
                        </span>
                        <pre
                            v-if="frame.snippet"
                            class="mt-1 overflow-x-auto"
                            >{{ frameCode(frame.snippet) }}</pre>
                    </li>
                </ul>
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
