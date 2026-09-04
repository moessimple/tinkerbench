<script setup lang="ts">
import { computed } from 'vue';
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

const props = defineProps<{
    entry: Extract<FeedItem, { kind: 'n_plus_one' }>;
}>();
defineEmits<{ navigate: [line: number] }>();

const copyText = computed(
    () =>
        `${props.entry.model}::${props.entry.relation} lazy-loaded ${props.entry.count}×\n` +
        `Eager-load with ->with('${props.entry.relation}')`,
);
</script>

<template>
    <Card
        label="N+1"
        :line="entry.line"
        variant="warning"
        :copy="copyText"
        @navigate="$emit('navigate', $event)"
    >
        <p>
            <strong class="text-warn">{{ entry.model }}</strong
            >::{{ entry.relation }} lazy-loaded {{ entry.count }}×
        </p>
        <p class="mt-1.5 text-xs text-muted">
            Eager-load with <code>-&gt;with('{{ entry.relation }}')</code>
        </p>
    </Card>
</template>
