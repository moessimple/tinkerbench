<script setup lang="ts">
import { computed } from 'vue';
import type { SnippetDebugPayload } from '@/types';

const props = defineProps<{ debug: SnippetDebugPayload }>();

function counted(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

const hasQueries = computed(() => props.debug.query_count > 0);
const hasHttpRequests = computed(() => props.debug.http_request_count > 0);

// Without a query or HTTP part, other would just repeat the snippet duration.
const hasBreakdown = computed(() => hasQueries.value || hasHttpRequests.value);

const queryLabel = computed(() => {
    const counts = [counted(props.debug.query_count, 'query', 'queries')];

    if (props.debug.duplicate_query_count > 0) {
        counts.push(`${props.debug.duplicate_query_count} duplicate`);
    }

    return `DB ${props.debug.query_duration_str} (${counts.join(', ')})`;
});

function milliseconds(value: number): string {
    return `${value.toFixed(2)}ms`;
}

// Always in milliseconds: the visible figures switch to seconds from 1s on and would no longer add up.
const calculation = computed(() => {
    const lines = [
        `${milliseconds(props.debug.run_duration_ms)} run = ${milliseconds(props.debug.boot_duration_ms)} boot + ${milliseconds(props.debug.duration_ms)} snippet`,
    ];

    if (hasBreakdown.value) {
        lines.push(
            `${milliseconds(props.debug.duration_ms)} snippet = ${milliseconds(props.debug.query_duration_ms)} DB + ${milliseconds(props.debug.http_duration_ms)} HTTP + ${milliseconds(props.debug.php_duration_ms)} other`,
        );
    }

    return lines.join('\n');
});

const httpLabel = computed(
    () =>
        `HTTP ${props.debug.http_duration_str} (${counted(props.debug.http_request_count, 'request', 'requests')})`,
);
</script>

<template>
    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span
            title="Loading and booting the target app before the snippet ran, on the console kernel (PHP's CLI runs without OPcache by default). Not comparable to a web request."
            >boot {{ debug.boot_duration_str }}</span
        >
        <span aria-hidden="true">·</span>
        <span class="font-medium text-fg" :title="calculation">{{
            debug.duration_str
        }}</span>
        <template v-if="hasQueries">
            <span aria-hidden="true">·</span>
            <span
                title="Time the database driver spent executing and fetching queries, the sum of the query cards. Building models from the rows counts as other."
                >{{ queryLabel }}</span
            >
        </template>
        <template v-if="hasHttpRequests">
            <span aria-hidden="true">·</span>
            <span
                title="Time from sending each HTTP client request to receiving its response, the sum of the HTTP cards, faked calls included."
                >{{ httpLabel }}</span
            >
        </template>
        <template v-if="hasBreakdown">
            <span aria-hidden="true">·</span>
            <span
                title="Snippet time minus DB and HTTP: PHP code, building models, cache, filesystem, and tinkerbench's own capture overhead."
                >other {{ debug.php_duration_str }}</span
            >
        </template>
        <span aria-hidden="true">·</span>
        <span class="font-medium text-fg">{{ debug.peak_memory_str }}</span>
    </span>
</template>
