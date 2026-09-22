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

// A GET/HEAD/DELETE call typically sends no body; an empty textual preview then carries nothing
// worth showing, unlike a non-null request_size, which always means an actual (non-textual) body.
const hasRequestBody = computed(() =>
    props.entry.request_body_preview !== null
        ? props.entry.request_body_preview !== ''
        : props.entry.request_size !== null,
);

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
        :copy="entry.response_body_preview ?? ''"
        @navigate="$emit('navigate', $event)"
    >
        <p>
            <strong>{{ entry.method }}</strong> {{ entry.url }}
        </p>
        <pre
            v-if="entry.response_body_preview !== null"
            class="mt-1.5 break-all whitespace-pre-wrap"
            >{{ entry.response_body_preview }}</pre>
        <p v-else class="mt-1.5 text-muted">
            {{ entry.response_content_type ?? 'unknown content type' }} ·
            {{ entry.response_size }} bytes
        </p>
        <details class="mt-2 text-xs">
            <summary
                class="cursor-pointer tracking-wide text-muted uppercase select-none hover:text-fg"
            >
                Details
            </summary>
            <div class="mt-1.5 flex flex-col gap-2">
                <div>
                    <p class="text-muted uppercase">Request</p>
                    <pre class="break-all whitespace-pre-wrap">{{
                        formatHeaders(entry.request_headers)
                    }}</pre>
                    <pre
                        v-if="
                            hasRequestBody &&
                            entry.request_body_preview !== null
                        "
                        class="mt-1 break-all whitespace-pre-wrap"
                        >{{ entry.request_body_preview }}</pre>
                    <p v-else-if="hasRequestBody" class="mt-1 text-muted">
                        {{
                            entry.request_content_type ?? 'unknown content type'
                        }}
                        · {{ entry.request_size }} bytes
                    </p>
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
                v-if="entry.faked"
                class="rounded bg-line/50 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-muted uppercase"
            >
                faked
            </span>
            <span
                v-if="entry.request_type !== 'Other'"
                class="rounded bg-line/50 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-muted uppercase"
            >
                {{ entry.request_type }}
            </span>
            <span
                v-if="entry.response_truncated"
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
