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
        :data-variant="variant"
        class="flex flex-col gap-2 border-b border-line px-4 py-3 font-mono text-sm"
        :class="{ 'text-red-400': variant === 'danger' }"
    >
        <header class="flex items-baseline justify-between gap-3">
            <span class="text-xs tracking-widest text-muted uppercase">
                {{ label }}
            </span>

            <button
                v-if="line !== null"
                type="button"
                class="shrink-0 text-xs text-accent hover:underline"
                @click="emit('navigate', line)"
            >
                line {{ line }}
            </button>
            <span v-else class="shrink-0 text-xs text-muted">no line</span>
        </header>

        <div class="min-w-0 overflow-x-auto">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="text-xs text-muted">
            <slot name="footer" />
        </footer>
    </article>
</template>
