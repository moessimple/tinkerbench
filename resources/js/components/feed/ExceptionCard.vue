<script setup lang="ts">
import type { ExceptionFrame, FeedItem } from '@/types';
import Card from '../Card.vue';

defineProps<{ entry: Extract<FeedItem, { kind: 'exception' }> }>();
defineEmits<{ navigate: [line: number] }>();

// A lone snippet frame adds nothing the card header (line N) doesn't already show.
function hasTrace(frames: ExceptionFrame[]): boolean {
    return frames.length > 1 || (frames.length === 1 && !frames[0].snippet);
}

function frameLocation(frame: ExceptionFrame): string {
    return frame.snippet
        ? `snippet:${frame.line}`
        : `${frame.file}:${frame.line}`;
}

function frameCountLabel(frames: ExceptionFrame[]): string {
    return `${frames.length} ${frames.length === 1 ? 'stack frame' : 'stack frames'}`;
}
</script>

<template>
    <Card
        label="Exception"
        :line="entry.line"
        variant="danger"
        @navigate="$emit('navigate', $event)"
    >
        <p>
            <strong class="text-danger">{{ entry.type }}</strong
            >: {{ entry.message }}
        </p>
        <details v-if="hasTrace(entry.frames)" class="mt-2 text-xs">
            <summary
                class="cursor-pointer tracking-wide text-muted uppercase select-none hover:text-fg"
            >
                {{ frameCountLabel(entry.frames) }}
            </summary>
            <ul class="mt-1.5 flex flex-col gap-0.5">
                <li
                    v-for="(frame, frameIndex) in entry.frames"
                    :key="frameIndex"
                    :data-vendor="frame.vendor"
                    :class="frame.vendor ? 'text-muted opacity-60' : 'text-fg'"
                >
                    {{ frameLocation(frame) }}
                    <span
                        v-if="frame.function && !frame.snippet"
                        class="text-muted"
                    >
                        · {{ frame.function }}
                    </span>
                </li>
            </ul>
        </details>
    </Card>
</template>
