<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        line: number | null;
        variant?: 'default' | 'danger' | 'warning';
        /** Plain text the copy button puts on the clipboard. Omit to hide the button. */
        copy?: string;
    }>(),
    { variant: 'default' },
);

const emit = defineEmits<{ navigate: [line: number] }>();

const copied = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyToClipboard(): Promise<void> {
    if (!navigator.clipboard || !props.copy) {
        return;
    }

    // A rejected write (denied permission, unfocused document) must not surface as an
    // unhandled rejection; the button just stays unconfirmed.
    try {
        await navigator.clipboard.writeText(props.copy);
    } catch {
        return;
    }

    copied.value = true;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(resetTimer));
</script>

<template>
    <article
        :data-label="label"
        :data-variant="variant"
        class="border-b border-l-2 border-line px-4 py-3"
        :class="{
            'border-l-danger': variant === 'danger',
            'border-l-warn': variant === 'warning',
            'border-l-transparent': variant === 'default',
        }"
    >
        <header
            class="flex items-baseline justify-between gap-3 text-xs tracking-wider text-muted uppercase"
        >
            <span>{{ label }}</span>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    v-if="copy"
                    type="button"
                    :aria-label="copied ? 'Copied' : 'Copy'"
                    class="-my-0.5 rounded px-1.5 py-1 text-muted normal-case hover:bg-line/50 hover:text-fg"
                    @click="copyToClipboard"
                >
                    <svg
                        v-if="copied"
                        viewBox="0 0 16 16"
                        width="14"
                        height="14"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"
                        />
                    </svg>
                    <svg
                        v-else
                        viewBox="0 0 16 16"
                        width="14"
                        height="14"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 0 1 0 1.5h-1.5a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-1.5a.75.75 0 0 1 1.5 0v1.5A1.75 1.75 0 0 1 9.25 16h-7.5A1.75 1.75 0 0 1 0 14.25Z"
                        />
                        <path
                            d="M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0 1 14.25 11h-7.5A1.75 1.75 0 0 1 5 9.25Zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25Z"
                        />
                    </svg>
                </button>

                <button
                    v-if="line !== null"
                    type="button"
                    class="-mx-1.5 -my-0.5 rounded px-1.5 py-1 text-accent normal-case hover:bg-line/50 hover:underline"
                    @click="emit('navigate', line)"
                >
                    line {{ line }}
                </button>
            </div>
        </header>

        <div class="mt-2 min-w-0 overflow-x-auto font-mono text-[15px] text-fg">
            <slot />
        </div>

        <footer
            v-if="$slots.footer"
            class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted"
        >
            <slot name="footer" />
        </footer>
    </article>
</template>
