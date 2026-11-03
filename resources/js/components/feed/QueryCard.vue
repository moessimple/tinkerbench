<script setup lang="ts">
import { computed } from 'vue';
import { formatSql, highlightSql } from '@/lib/sql';
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

const props = defineProps<{ entry: Extract<FeedItem, { kind: 'query' }> }>();
defineEmits<{ navigate: [line: number] }>();

const formatted = computed(() => formatSql(props.entry.sql));
const highlighted = computed(() => highlightSql(formatted.value));
</script>

<template>
    <Card
        label="Query"
        :variant="entry.slow ? 'warning' : 'default'"
        :line="entry.line"
        :copy="formatted"
        @navigate="$emit('navigate', $event)"
    >
        <pre class="whitespace-pre" v-html="highlighted" />
        <template #footer>
            <span
                v-if="entry.slow"
                class="rounded-sm bg-warn/15 px-1.5 py-0.5 font-medium text-warn"
            >
                slow
            </span>
            <span
                v-if="entry.duplicate"
                class="rounded-sm bg-warn/15 px-1.5 py-0.5 font-medium text-warn"
            >
                duplicate
            </span>
            <span>{{ entry.duration_str }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ entry.connection }}</span>
        </template>
    </Card>
</template>
