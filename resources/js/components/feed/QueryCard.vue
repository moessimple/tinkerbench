<script setup lang="ts">
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

defineProps<{ entry: Extract<FeedItem, { kind: 'query' }> }>();
defineEmits<{ navigate: [line: number] }>();
</script>

<template>
    <Card
        label="Query"
        :variant="entry.slow ? 'warning' : 'default'"
        :line="entry.line"
        @navigate="$emit('navigate', $event)"
    >
        <code class="block break-all">{{ entry.sql }}</code>
        <template #footer>
            <span
                v-if="entry.slow"
                class="rounded bg-warn/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-warn uppercase"
            >
                slow
            </span>
            <span
                v-if="entry.duplicate"
                class="rounded bg-warn/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-warn uppercase"
            >
                duplicate
            </span>
            <span>{{ entry.duration_str }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ entry.connection }}</span>
        </template>
    </Card>
</template>
