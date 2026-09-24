<script setup lang="ts">
import { computed } from 'vue';
import type { SnippetDebugPayload } from '@/types';

const props = defineProps<{ debug: SnippetDebugPayload }>();

interface RunPart {
    label: string;
    milliseconds: number;
    text: string;
    hint: string;
    swatch: string;
}

function counted(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

// Boot + DB + HTTP + PHP adds up to the run time exactly, so the legend is the whole calculation.
const parts = computed<RunPart[]>(() => {
    const parts: RunPart[] = [
        {
            label: 'Boot',
            milliseconds: props.debug.boot_duration_ms,
            text: `Boot ${props.debug.boot_duration_str}`,
            hint: 'Console boot, not a web request',
            swatch: 'bg-muted/50',
        },
    ];

    if (props.debug.query_count > 0) {
        const counts = [counted(props.debug.query_count, 'query', 'queries')];

        if (props.debug.duplicate_query_count > 0) {
            counts.push(`${props.debug.duplicate_query_count} duplicate`);
        }

        parts.push({
            label: 'DB',
            milliseconds: props.debug.query_duration_ms,
            text: `DB ${props.debug.query_duration_str} (${counts.join(', ')})`,
            hint: 'Sum of the query cards',
            swatch: 'bg-accent',
        });
    }

    if (props.debug.http_request_count > 0) {
        parts.push({
            label: 'HTTP',
            milliseconds: props.debug.http_duration_ms,
            text: `HTTP ${props.debug.http_duration_str} (${counted(props.debug.http_request_count, 'request', 'requests')})`,
            hint: 'Time at least one request was in flight, so parallel requests count once',
            swatch: 'bg-warn',
        });
    }

    parts.push({
        label: 'PHP',
        milliseconds: props.debug.php_duration_ms,
        text: `PHP ${props.debug.php_duration_str}`,
        hint: 'Everything else, mostly loading classes',
        swatch: 'bg-fg/85',
    });

    return parts;
});

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
</script>

<template>
    <section
        aria-label="Run time"
        class="flex shrink-0 flex-col gap-y-1.5 border-b border-line px-4 py-2 text-xs text-muted"
    >
        <div class="flex items-baseline justify-between gap-x-3">
            <span class="font-medium text-fg">{{
                debug.run_duration_str
            }}</span>
            <span class="font-medium text-fg">{{ debug.peak_memory_str }}</span>
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
                :title="part.hint"
            >
                <span
                    aria-hidden="true"
                    class="size-2 shrink-0 rounded-sm"
                    :class="part.swatch"
                />
                <span>{{ part.text }}</span>
            </li>
        </ul>
    </section>
</template>
