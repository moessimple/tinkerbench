<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        line: number | null;
        variant?: 'default' | 'danger';
    }>(),
    { variant: 'default' },
);

const emit = defineEmits<{ navigate: [line: number] }>();
</script>

<template>
    <article
        :data-label="label"
        :data-variant="variant"
        class="border-b border-l-2 border-line px-4 py-3 font-mono"
        :class="
            variant === 'danger' ? 'border-l-danger' : 'border-l-transparent'
        "
    >
        <header
            class="flex items-baseline justify-between gap-3 text-[11px] tracking-wider text-muted uppercase"
        >
            <span>{{ label }}</span>

            <button
                v-if="line !== null"
                type="button"
                class="-mx-1 shrink-0 rounded px-1 text-accent normal-case hover:bg-line/50 hover:underline"
                @click="emit('navigate', line)"
            >
                line {{ line }}
            </button>
        </header>

        <div class="mt-2 min-w-0 overflow-x-auto text-sm text-fg">
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
