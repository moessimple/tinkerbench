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
        <!--
            Snippet stdout is untrusted (see detectOutput). HTML-looking output renders only in
            this sandboxed iframe so its scripts stay isolated from tinkerbench's page; the JSON
            branch below is pre-escaped by highlightJson, so its v-html is safe.
        -->
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
        <pre v-else class="whitespace-pre-wrap">{{ entry.text }}</pre>
    </Card>
</template>
