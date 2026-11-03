<script setup lang="ts">
import type { SnippetDebugPayload } from '@/types';

defineProps<{ debug: SnippetDebugPayload }>();
</script>

<template>
    <section
        aria-label="Run time"
        class="flex shrink-0 flex-wrap items-baseline justify-between gap-x-3 border-b border-line px-4 py-2 font-mono text-sm font-medium text-fg tabular-nums"
    >
        <span class="whitespace-nowrap"
            ><span class="font-sans font-normal text-muted">Duration</span>
            {{ debug.run_duration_str }}</span
        >
        <span class="whitespace-nowrap" title="Peak memory usage during the run"
            ><span class="font-sans font-normal text-muted">Memory usage</span>
            {{ debug.peak_memory_str }}</span
        >
    </section>
</template>
