<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

defineProps<{
    watchers: { id: string; label: string; enabled: boolean }[];
}>();
const emit = defineEmits<{ toggle: [id: string] }>();

const isOpen = ref(false);
const rootRef = useTemplateRef<HTMLElement>('root');

function close(): void {
    isOpen.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (isOpen.value && !rootRef.value?.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (isOpen.value && event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            title="Optional watchers"
            aria-label="Optional watchers"
            :aria-expanded="isOpen"
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-muted hover:bg-line/40 hover:text-fg"
            @click="isOpen = !isOpen"
        >
            <svg
                viewBox="0 0 16 16"
                width="13"
                height="13"
                fill="none"
                stroke="currentColor"
                stroke-width="1.4"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <circle cx="8" cy="8" r="2.25" />
                <path
                    d="M8 1.5v1.6M8 12.9v1.6M14.5 8h-1.6M3.1 8H1.5M12.5 3.5l-1.13 1.13M4.63 11.37 3.5 12.5M12.5 12.5l-1.13-1.13M4.63 4.63 3.5 3.5"
                />
            </svg>
        </button>

        <div
            v-if="isOpen"
            role="menu"
            aria-label="Optional watchers"
            class="absolute right-0 z-10 mt-1 w-40 rounded-md border border-line bg-surface p-1 text-xs shadow-lg"
        >
            <label
                v-for="watcher in watchers"
                :key="watcher.id"
                class="flex items-center gap-2 rounded px-2 py-1.5 text-fg hover:bg-line/30"
            >
                <input
                    type="checkbox"
                    :checked="watcher.enabled"
                    @change="emit('toggle', watcher.id)"
                />
                {{ watcher.label }}
            </label>
        </div>
    </div>
</template>
