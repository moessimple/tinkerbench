<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        line: number | null;
        variant?: 'default' | 'danger' | 'warning';
    }>(),
    { variant: 'default' },
);

const emit = defineEmits<{ navigate: [line: number] }>();
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

            <button
                v-if="line !== null"
                type="button"
                class="-mx-1.5 -my-0.5 shrink-0 rounded px-1.5 py-1 text-accent normal-case hover:bg-line/50 hover:underline"
                @click="emit('navigate', line)"
            >
                line {{ line }}
            </button>
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
