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

const httpLabel = computed(
    () =>
        `HTTP ${props.debug.http_duration_str} (${counted(props.debug.http_request_count, 'request', 'requests')})`,
);
</script>

<template>
    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="font-medium text-fg">{{ debug.duration_str }}</span>
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
                >other {{ debug.other_duration_str }}</span
            >
        </template>
        <span aria-hidden="true">·</span>
        <span class="font-medium text-fg">{{ debug.peak_memory_str }}</span>
    </span>
</template>
