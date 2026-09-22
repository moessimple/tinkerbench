<script setup lang="ts">
import { computed } from 'vue';
import type { FeedItem } from '@/types';
import Card from '../Card.vue';

const props = defineProps<{
    entry: Extract<FeedItem, { kind: 'http_client' }>;
}>();
defineEmits<{ navigate: [line: number] }>();

const variant = computed(() => {
    if (props.entry.status >= 500) {
        return 'danger';
    }

    if (props.entry.status >= 400) {
        return 'warning';
    }

    return 'default';
});

function formatHeaders(headers: Record<string, string[]>): string {
    return Object.entries(headers)
        .map(([name, values]) => `${name}: ${values.join(', ')}`)
        .join('\n');
}
</script>

<template>
    <Card
        label="HTTP"
        :variant="variant"
        :line="entry.line"
        :copy="entry.body_preview ?? ''"
        @navigate="$emit('navigate', $event)"
    >
        <p>
            <strong>{{ entry.method }}</strong> {{ entry.url }}
        </p>
        <pre
            v-if="entry.body_preview !== null"
            class="mt-1.5 break-all whitespace-pre-wrap"
            >{{ entry.body_preview }}</pre>
        <p v-else class="mt-1.5 text-muted">
            {{ entry.content_type ?? 'unknown content type' }} ·
            {{ entry.size }} bytes
        </p>
        <details class="mt-2 text-xs">
            <summary
                class="cursor-pointer tracking-wide text-muted uppercase select-none hover:text-fg"
            >
                Headers
            </summary>
            <div class="mt-1.5 flex flex-col gap-2">
                <div>
                    <p class="text-muted uppercase">Request</p>
                    <pre class="break-all whitespace-pre-wrap">{{
                        formatHeaders(entry.request_headers)
                    }}</pre>
                </div>
                <div>
                    <p class="text-muted uppercase">Response</p>
                    <pre class="break-all whitespace-pre-wrap">{{
                        formatHeaders(entry.response_headers)
                    }}</pre>
                </div>
            </div>
        </details>
        <template #footer>
            <span
                v-if="entry.truncated"
                class="rounded bg-warn/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-warn uppercase"
            >
                truncated
            </span>
            <span>{{ entry.status }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ entry.duration_str }}</span>
        </template>
    </Card>
</template>
