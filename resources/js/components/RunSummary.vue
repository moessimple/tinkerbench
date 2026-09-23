<script setup lang="ts">
import { computed } from 'vue';
import type { SnippetDebugPayload } from '@/types';

const props = defineProps<{ debug: SnippetDebugPayload }>();

interface RunPart {
    label: string;
    milliseconds: number;
    text: string;
    definition: string;
    swatch: string;
}

function counted(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

function milliseconds(value: number): string {
    return `${value.toFixed(2)}ms`;
}

const hasQueries = computed(() => props.debug.query_count > 0);
const hasHttpRequests = computed(() => props.debug.http_request_count > 0);

// Without a query or HTTP part, a PHP part would just repeat the snippet time.
const hasBreakdown = computed(() => hasQueries.value || hasHttpRequests.value);

const bootPart = computed<RunPart>(() => ({
    label: 'boot',
    milliseconds: props.debug.boot_duration_ms,
    text: `boot ${props.debug.boot_duration_str}`,
    definition:
        "Loading and booting the target app before the snippet runs, on the console kernel. PHP's CLI runs without OPcache by default, so this is not comparable to a web request.",
    swatch: 'bg-muted/50',
}));

const snippetParts = computed<RunPart[]>(() => {
    if (!hasBreakdown.value) {
        return [
            {
                label: 'snippet',
                milliseconds: props.debug.duration_ms,
                text: `snippet ${props.debug.duration_str}`,
                definition: 'Running the snippet itself.',
                swatch: 'bg-fg/85',
            },
        ];
    }

    const parts: RunPart[] = [];

    if (hasQueries.value) {
        const counts = [counted(props.debug.query_count, 'query', 'queries')];

        if (props.debug.duplicate_query_count > 0) {
            counts.push(`${props.debug.duplicate_query_count} duplicate`);
        }

        parts.push({
            label: 'DB',
            milliseconds: props.debug.query_duration_ms,
            text: `DB ${props.debug.query_duration_str} (${counts.join(', ')})`,
            definition:
                'Time the database driver spent executing and fetching queries, the sum of the query cards.',
            swatch: 'bg-accent',
        });
    }

    if (hasHttpRequests.value) {
        parts.push({
            label: 'HTTP',
            milliseconds: props.debug.http_duration_ms,
            text: `HTTP ${props.debug.http_duration_str} (${counted(props.debug.http_request_count, 'request', 'requests')})`,
            definition:
                'Time from sending each HTTP client request to receiving its response, the sum of the HTTP cards, faked calls included.',
            swatch: 'bg-warn',
        });
    }

    parts.push({
        label: 'PHP',
        milliseconds: props.debug.php_duration_ms,
        text: `PHP ${props.debug.php_duration_str}`,
        definition:
            "The rest of the snippet time, spent in the PHP process: the snippet and framework code, building models from rows, cache and file access, and loading classes, which PHP's CLI compiles on first use without OPcache by default. Includes tinkerbench's own capture overhead.",
        swatch: 'bg-fg/85',
    });

    return parts;
});

const parts = computed(() => [bootPart.value, ...snippetParts.value]);

// A negative PHP time (a query inside a faked HTTP callback counts in both) still shows its value
// in the legend, but has no bar length to draw.
function share(part: RunPart): number {
    if (props.debug.run_duration_ms <= 0) {
        return 0;
    }

    return (Math.max(part.milliseconds, 0) / props.debug.run_duration_ms) * 100;
}

const barLabel = computed(
    () =>
        `${parts.value.map((part) => `${part.label} ${share(part).toFixed(1)}%`).join(', ')} of the run`,
);

// Always in milliseconds: the visible figures switch to seconds from 1s on and would no longer add up.
const calculation = computed(() => {
    const lines = [
        `${milliseconds(props.debug.run_duration_ms)} run = ${milliseconds(props.debug.boot_duration_ms)} boot + ${milliseconds(props.debug.duration_ms)} snippet`,
    ];

    if (hasBreakdown.value) {
        lines.push(
            `${milliseconds(props.debug.duration_ms)} snippet = ${snippetParts.value.map((part) => `${milliseconds(part.milliseconds)} ${part.label}`).join(' + ')}`,
        );
    }

    return lines;
});
</script>

<template>
    <section
        aria-label="Run time"
        class="flex shrink-0 flex-col gap-y-1.5 border-b border-line px-4 py-2 text-xs text-muted"
    >
        <div class="flex items-baseline justify-between gap-x-3">
            <span
                ><span class="font-medium text-fg">{{
                    debug.run_duration_str
                }}</span>
                run</span
            >
            <span
                >peak memory
                <span class="font-medium text-fg">{{
                    debug.peak_memory_str
                }}</span></span
            >
        </div>
        <div
            role="img"
            :aria-label="barLabel"
            class="flex h-1.5 gap-px overflow-hidden rounded-full bg-line"
        >
            <span
                v-for="part in parts"
                :key="part.label"
                :class="part.swatch"
                :style="{ width: `${Number(share(part).toFixed(4))}%` }"
            />
        </div>
        <ul class="flex flex-wrap gap-x-3 gap-y-1">
            <li
                v-for="part in parts"
                :key="part.label"
                class="flex items-center gap-x-1.5"
            >
                <span
                    aria-hidden="true"
                    class="size-2 shrink-0 rounded-sm"
                    :class="part.swatch"
                />
                <span>{{ part.text }}</span>
            </li>
        </ul>
        <details>
            <summary class="w-fit cursor-pointer select-none hover:text-fg">
                How it adds up
            </summary>
            <div class="mt-1.5 flex flex-col gap-y-2">
                <div class="font-mono text-fg">
                    <p v-for="line in calculation" :key="line">{{ line }}</p>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    <template v-for="part in parts" :key="part.label">
                        <dt class="font-medium text-fg">{{ part.label }}</dt>
                        <dd>{{ part.definition }}</dd>
                    </template>
                </dl>
            </div>
        </details>
    </section>
</template>
