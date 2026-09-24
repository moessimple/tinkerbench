<script setup lang="ts">
import { computed } from 'vue';
import type { TimedKind } from '@/composables/useWatcherToggles';
import type { SnippetDebugPayload } from '@/types';

const props = defineProps<{
    debug: SnippetDebugPayload;
    /** Kinds whose watcher did not run, so their time is part of the code time. */
    unmeasured: TimedKind[];
}>();

interface Part {
    label: string;
    hint: string;
    durationMs: number;
    durationStr: string;
    percentage: number;
    swatch: string;
    note: string;
}

const LABELS: Record<TimedKind, string> = { query: 'DB', http_client: 'HTTP' };

// Shares are of the application time, not the whole run: boot is the same on every run and would
// otherwise dwarf what the snippet itself does.
function shareOfApplication(milliseconds: number): number {
    if (props.debug.application_duration_ms <= 0) {
        return 0;
    }

    return (milliseconds / props.debug.application_duration_ms) * 100;
}

// Rounded the way the runner rounds durations: DB and HTTP round on their own and code takes the
// remainder, so the shown percentages add up to 100. A negative code time gets a negative share.
const percentages = computed<Record<TimedKind | 'code', number>>(() => {
    if (props.debug.application_duration_ms <= 0) {
        return { query: 0, http_client: 0, code: 0 };
    }

    const query = Math.round(shareOfApplication(props.debug.query_duration_ms));
    const httpClient = Math.round(
        shareOfApplication(props.debug.http_duration_ms),
    );

    return { query, http_client: httpClient, code: 100 - query - httpClient };
});

function codeNote(): string {
    if (props.debug.code_duration_ms < 0) {
        return 'negative, since a query inside a faked HTTP callback counts as both DB and HTTP';
    }

    if (props.unmeasured.length > 0) {
        return `includes ${props.unmeasured.map((kind) => LABELS[kind]).join(' and ')} (not measured)`;
    }

    return '';
}

const parts = computed<Part[]>(() => {
    const parts: Part[] = [];

    if (props.debug.query_count > 0) {
        parts.push({
            label: LABELS.query,
            hint: 'Sum of the query cards',
            durationMs: props.debug.query_duration_ms,
            durationStr: props.debug.query_duration_str,
            percentage: percentages.value.query,
            swatch: 'bg-accent',
            note: '',
        });
    }

    if (props.debug.http_request_count > 0) {
        parts.push({
            label: LABELS.http_client,
            hint: 'Time at least one request was in flight, so parallel requests count once',
            durationMs: props.debug.http_duration_ms,
            durationStr: props.debug.http_duration_str,
            percentage: percentages.value.http_client,
            swatch: 'bg-warn',
            note: '',
        });
    }

    parts.push({
        label: 'Code',
        hint: 'Time in the snippet’s own PHP, outside the measured DB and HTTP time',
        durationMs: props.debug.code_duration_ms,
        durationStr: props.debug.code_duration_str,
        percentage: percentages.value.code,
        swatch: 'bg-fg/70',
        note: codeNote(),
    });

    return parts;
});
</script>

<template>
    <div class="flex flex-col gap-3 px-4 py-3 text-xs">
        <p
            class="flex items-baseline justify-between gap-x-3 text-fg"
            title="Console boot, not a web request. The same on every run, so it is not part of the shares below."
        >
            <span class="font-medium">Booting</span>
            <span class="font-mono">{{ debug.boot_duration_str }}</span>
        </p>
        <p class="flex items-baseline justify-between gap-x-3 text-fg">
            <span class="font-medium">Application</span>
            <span class="font-mono">{{ debug.application_duration_str }}</span>
        </p>
        <ul aria-label="Timeline" class="-mt-1 flex flex-col gap-2 pl-4">
            <li
                v-for="part in parts"
                :key="part.label"
                class="flex flex-col gap-1"
                :title="part.hint"
            >
                <div
                    class="grid grid-cols-[3rem_5.5rem_3rem_1fr] items-center gap-x-3"
                >
                    <span class="font-medium text-fg">{{ part.label }}</span>
                    <span class="text-right font-mono text-fg">{{
                        part.durationStr
                    }}</span>
                    <span class="text-right font-mono text-muted"
                        >{{ part.percentage }}%</span
                    >
                    <span class="h-2 overflow-hidden rounded-full bg-line">
                        <span
                            aria-hidden="true"
                            class="block h-full rounded-full"
                            :class="part.swatch"
                            :style="{
                                width: `${Number(shareOfApplication(Math.max(part.durationMs, 0)).toFixed(4))}%`,
                            }"
                        />
                    </span>
                </div>
                <span v-if="part.note" class="block pl-15 text-muted">{{
                    part.note
                }}</span>
            </li>
        </ul>
        <p
            class="flex items-baseline justify-between gap-x-3 border-t border-line pt-2 font-medium text-fg"
        >
            <span>Duration</span>
            <span class="font-mono">{{ debug.run_duration_str }}</span>
        </p>
    </div>
</template>
