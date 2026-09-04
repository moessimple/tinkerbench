<script setup lang="ts">
import { computed } from 'vue';
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

const SEVERE_LOG_LEVELS = ['emergency', 'alert', 'critical', 'error'];

const props = defineProps<{ entry: Extract<FeedItem, { kind: 'log' }> }>();
defineEmits<{ navigate: [line: number] }>();

const isSevere = computed(() => SEVERE_LOG_LEVELS.includes(props.entry.label));

const copyText = computed(() =>
    props.entry.context_text
        ? `${props.entry.message}\n${props.entry.context_text}`
        : props.entry.message,
);
</script>

<template>
    <Card
        label="Log"
        :line="entry.line"
        :variant="isSevere ? 'danger' : 'default'"
        :copy="copyText"
        @navigate="$emit('navigate', $event)"
    >
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span
                class="text-[10px] tracking-wide uppercase"
                :class="isSevere ? 'text-danger' : 'text-muted'"
            >
                {{ entry.label }}
            </span>
            <span class="break-all">{{ entry.message }}</span>
        </div>
        <div
            v-if="entry.context_html"
            class="mt-1 min-w-0 overflow-x-auto text-xs"
            v-html="entry.context_html"
        />
    </Card>
</template>
