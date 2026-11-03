<script setup lang="ts">
import { computed, ref } from 'vue';
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

// A full HTML page or a cookie jar would otherwise outgrow the rest of the feed. The preview cuts the
// text itself instead of clipping it with CSS, so nothing is hidden without the "Show all" button.
const PREVIEW_LINES = 10;
const PREVIEW_CHARACTERS = 600;

type SectionKey =
    | 'request_headers'
    | 'request_body'
    | 'response_headers'
    | 'response_body';

interface Section {
    key: SectionKey;
    label: string;
    text: string;
    truncated: boolean;
}

const sections = computed<Section[]>(() => [
    {
        key: 'request_headers',
        label: 'Request headers',
        text: formatHeaders(props.entry.request_headers),
        truncated: false,
    },
    {
        key: 'request_body',
        label: 'Request body',
        text: formatBody(
            props.entry.request_body_preview,
            props.entry.request_content_type,
            props.entry.request_size,
        ),
        truncated: props.entry.request_truncated,
    },
    {
        key: 'response_headers',
        label: 'Response headers',
        text: formatHeaders(props.entry.response_headers),
        truncated: false,
    },
    {
        key: 'response_body',
        label: 'Response body',
        text: formatBody(
            props.entry.response_body_preview,
            props.entry.response_content_type,
            props.entry.response_size,
        ),
        truncated: props.entry.response_truncated,
    },
]);

const expandedSections = ref(new Set<SectionKey>());

function isLong(text: string): boolean {
    return (
        text.split('\n').length > PREVIEW_LINES ||
        text.length > PREVIEW_CHARACTERS
    );
}

function shownText(section: Section): string {
    if (!isLong(section.text) || expandedSections.value.has(section.key)) {
        return section.text;
    }

    return section.text
        .split('\n')
        .slice(0, PREVIEW_LINES)
        .join('\n')
        .slice(0, PREVIEW_CHARACTERS);
}

function toggleSection(key: SectionKey): void {
    const expanded = new Set(expandedSections.value);

    if (expanded.has(key)) {
        expanded.delete(key);
    } else {
        expanded.add(key);
    }

    expandedSections.value = expanded;
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
            <div
                v-for="(section, index) in sections"
                :key="section.key"
                class="flex flex-col gap-1 py-1.5"
                :class="{
                    'border-b border-line/50': index < sections.length - 1,
                }"
            >
                <dt class="font-sans text-xs text-muted">
                    {{ section.label }}
                </dt>
                <dd>
                    <pre class="break-all whitespace-pre-wrap text-fg">{{
                        shownText(section)
                    }}</pre>
                    <button
                        v-if="isLong(section.text)"
                        type="button"
                        :aria-expanded="expandedSections.has(section.key)"
                        class="mt-1 -ml-1.5 rounded px-1.5 py-0.5 font-sans text-xs text-muted hover:bg-line/50 hover:text-fg"
                        @click="toggleSection(section.key)"
                    >
                        {{
                            expandedSections.has(section.key)
                                ? 'Show less'
                                : 'Show all'
                        }}
                    </button>
                    <p v-if="section.truncated" class="mt-1 text-xs text-warn">
                        truncated
                    </p>
                </dd>
            </div>
        </dl>
    </Card>
</template>
