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
    const entries = Object.entries(headers);

    if (entries.length === 0) {
        return 'none';
    }

    return entries
        .map(([name, values]) => `${name}: ${values.join(', ')}`)
        .join('\n');
}

function formatBody(
    preview: string | null,
    contentType: string | null,
    size: number | null,
): string {
    if (preview !== null) {
        return preview;
    }

    const type = contentType ?? 'unknown content type';

    return size === null ? type : `${type} · ${size} bytes`;
}
</script>

<template>
    <Card
        label="HTTP"
        :variant="variant"
        :line="entry.line"
        :copy="entry.response_body_preview ?? ''"
        @navigate="$emit('navigate', $event)"
    >
        <dl class="flex flex-col text-sm">
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-xs text-muted">URL</dt>
                <dd class="break-all text-fg">
                    {{ entry.method }} {{ entry.url }}
                </dd>
            </div>
            <div
                class="flex items-baseline justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-xs text-muted">Type</dt>
                <dd class="text-fg">{{ entry.request_type }}</dd>
            </div>
            <div
                class="flex items-baseline justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-xs text-muted">
                    Real request
                </dt>
                <dd class="text-fg">{{ entry.faked ? 'No' : 'Yes' }}</dd>
            </div>
            <div
                class="flex items-baseline justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-xs text-muted">Success</dt>
                <dd class="text-fg">{{ entry.status < 400 ? 'Yes' : 'No' }}</dd>
            </div>
            <div
                class="flex items-baseline justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-xs text-muted">Status</dt>
                <dd class="text-fg">{{ entry.status }}</dd>
            </div>
            <div
                class="flex items-baseline justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-xs text-muted">Duration</dt>
                <dd class="text-fg">{{ entry.duration_str }}</dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-xs text-muted">Request headers</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatHeaders(entry.request_headers)
                    }}</pre>
                </dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-xs text-muted">Request body</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatBody(
                            entry.request_body_preview,
                            entry.request_content_type,
                            entry.request_size,
                        )
                    }}</pre>
                    <p
                        v-if="entry.request_truncated"
                        class="mt-1 text-xs text-warn"
                    >
                        truncated
                    </p>
                </dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-xs text-muted">Response headers</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatHeaders(entry.response_headers)
                    }}</pre>
                </dd>
            </div>
            <div class="flex flex-col gap-1 py-1.5">
                <dt class="font-sans text-xs text-muted">Response body</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatBody(
                            entry.response_body_preview,
                            entry.response_content_type,
                            entry.response_size,
                        )
                    }}</pre>
                    <p
                        v-if="entry.response_truncated"
                        class="mt-1 text-xs text-warn"
                    >
                        truncated
                    </p>
                </dd>
            </div>
        </dl>
    </Card>
</template>
