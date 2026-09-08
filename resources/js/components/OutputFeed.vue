<script setup lang="ts">
import { computed, nextTick, useTemplateRef, watch } from 'vue';
import type { FeedEntry, FeedFilter, FeedSort } from '@/lib/feed';
import { executeScripts } from '@/lib/output';
import { FACET_KINDS, rendererFor } from './feed/kinds';

const props = withDefaults(
    defineProps<{
        items: FeedEntry[];
        filter?: FeedFilter;
        sort?: FeedSort;
    }>(),
    { filter: 'all', sort: 'recent' },
);

defineEmits<{ navigate: [line: number] }>();

const feedElement = useTemplateRef('feedElement');

function entryDurationMs(entry: FeedEntry): number {
    return entry.kind === 'query' ? entry.duration_ms : 0;
}

const rows = computed(() => {
    const visible = props.items.filter(
        (entry) => props.filter === 'all' || entry.kind === props.filter,
    );

    return props.sort === 'slowest'
        ? [...visible].sort((a, b) => entryDurationMs(b) - entryDurationMs(a))
        : visible;
});

const emptyLabel = computed(() => {
    const match = FACET_KINDS.find((kind) => kind.kind === props.filter);

    return match ? `No ${match.facet.toLowerCase()} on this run` : '';
});

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
            {{ emptyLabel }}
        </p>

        <component
            :is="rendererFor(entry.kind)"
            v-for="(entry, index) in rows"
            :key="index"
            :entry="entry"
            @navigate="$emit('navigate', $event)"
        />
    </div>
</template>
