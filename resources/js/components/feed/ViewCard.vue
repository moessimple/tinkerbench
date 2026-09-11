<script setup lang="ts">
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

defineProps<{ entry: Extract<FeedItem, { kind: 'view' }> }>();
defineEmits<{ navigate: [line: number] }>();
</script>

<template>
    <Card
        label="View"
        :line="entry.line"
        :copy="entry.data_text"
        @navigate="$emit('navigate', $event)"
    >
        <div class="min-w-0" v-html="entry.data_html" />
        <template #footer>
            <span class="break-all">{{ entry.path }}</span>
        </template>
    </Card>
</template>
