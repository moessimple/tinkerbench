<script setup lang="ts">
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

defineProps<{ entry: Extract<FeedItem, { kind: 'dump' }> }>();
defineEmits<{ navigate: [line: number] }>();
</script>

<template>
    <Card
        label="Dump"
        :line="entry.line"
        :copy="entry.text"
        @navigate="$emit('navigate', $event)"
    >
        <div class="min-w-0" v-html="entry.html" />
    </Card>
</template>
