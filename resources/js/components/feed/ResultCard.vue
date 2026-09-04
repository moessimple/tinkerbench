<script setup lang="ts">
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

defineProps<{ entry: Extract<FeedItem, { kind: 'result' }> }>();
defineEmits<{ navigate: [line: number] }>();
</script>

<template>
    <Card label="Result" :line="null" :copy="entry.text">
        <div class="min-w-0" v-html="entry.html" />
    </Card>
</template>
