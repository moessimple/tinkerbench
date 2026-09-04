<script setup lang="ts">
import { computed } from 'vue';
import type { OutputItem } from '@/lib/feed';
import { detectOutput, highlightJson } from '@/lib/output';
import Card from '../Card.vue';

const props = defineProps<{ entry: OutputItem }>();
defineEmits<{ navigate: [line: number] }>();

const output = computed(() => detectOutput(props.entry.text));
</script>

<template>
    <Card label="Output" :line="null" :copy="entry.text">
        <iframe
            v-if="output.type === 'html'"
            class="h-64 w-full border-0 bg-white"
            sandbox="allow-scripts"
            title="Rendered HTML output"
            :srcdoc="entry.text"
        />
        <pre
            v-else-if="output.type === 'json'"
            class="whitespace-pre-wrap"
            v-html="highlightJson(output.pretty)"
        />
        <div v-else-if="output.type === 'dump'" v-html="entry.text" />
        <pre v-else class="whitespace-pre-wrap">{{ entry.text }}</pre>
    </Card>
</template>
