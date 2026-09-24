<script setup lang="ts">
import type { SnippetDebugPayload } from '@/types';

defineProps<{ debug: SnippetDebugPayload }>();
</script>

<template>
    <section
        aria-label="Run time"
        class="flex shrink-0 items-baseline justify-between gap-x-3 border-b border-line px-4 py-2 text-xs font-medium text-fg"
    >
        <span
            ><span class="font-normal text-muted">Duration</span>
            {{ debug.run_duration_str }}</span
        >
        <span title="Peak memory usage during the run"
            ><span class="font-normal text-muted">Memory Usage</span>
            {{ debug.peak_memory_str }}</span
        >
    </section>
</template>
