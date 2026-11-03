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
    return (
        preview ?? `${contentType ?? 'unknown content type'} · ${size} bytes`
    );
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
        <dl class="flex flex-col text-xs">
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">URL</dt>
                <dd class="text-right break-all text-fg">
                    {{ entry.method }} {{ entry.url }}
                </dd>
            </div>
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">Type</dt>
                <dd class="text-fg">{{ entry.request_type }}</dd>
            </div>
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">Real Request</dt>
                <dd class="text-fg">{{ !entry.faked }}</dd>
            </div>
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">Success</dt>
                <dd class="text-fg">{{ entry.status < 400 }}</dd>
            </div>
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">Status</dt>
                <dd class="text-fg">{{ entry.status }}</dd>
            </div>
            <div
                class="flex items-start justify-between gap-3 border-b border-line/50 py-1.5"
            >
                <dt class="shrink-0 font-sans text-muted">Duration</dt>
                <dd class="text-fg">{{ entry.duration_str }}</dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-muted">Request Headers</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatHeaders(entry.request_headers)
                    }}</pre>
                </dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-line/50 py-1.5">
                <dt class="font-sans text-muted">Request Body</dt>
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
                <dt class="font-sans text-muted">Response Headers</dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        formatHeaders(entry.response_headers)
                    }}</pre>
                </dd>
            </div>
            <div class="flex flex-col gap-1 py-1.5">
                <dt class="font-sans text-muted">Response Body</dt>
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
