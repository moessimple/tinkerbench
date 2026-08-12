<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { shortcuts } from '@/lib/shortcuts';

const isOpen = ref(false);

function toggle(): void {
    isOpen.value = !isOpen.value;
}

function close(): void {
    isOpen.value = false;
}

function isTextInputFocused(): boolean {
    const active = document.activeElement;

    return (
        active instanceof HTMLInputElement ||
        active instanceof HTMLTextAreaElement
    );
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (event.key === '?' && !isTextInputFocused()) {
        event.preventDefault();
        toggle();

        return;
    }

    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

onMounted(() =>
    window.addEventListener('keydown', onGlobalKeydown, { capture: true }),
);
onBeforeUnmount(() =>
    window.removeEventListener('keydown', onGlobalKeydown, { capture: true }),
);
</script>

<template>
    <div class="relative">
        <button
            type="button"
            title="Keyboard shortcuts (?)"
            aria-label="Keyboard shortcuts"
            class="flex h-8 w-8 items-center justify-center rounded font-mono text-sm text-muted hover:bg-line/30 hover:text-fg"
            @click="toggle"
        >
            ?
        </button>

        <div
            v-if="isOpen"
            class="fixed inset-0 z-20 flex justify-center bg-black/50 pt-[15vh]"
            @click.self="close"
        >
            <div
                role="dialog"
                aria-label="Keyboard shortcuts"
                class="w-full max-w-xs rounded-md border border-line bg-surface p-3 shadow-2xl"
            >
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="shortcut in shortcuts"
                        :key="shortcut.description"
                        class="flex items-center justify-between gap-4 font-mono text-sm text-fg"
                    >
                        <span>{{ shortcut.description }}</span>
                        <kbd
                            class="rounded border border-line bg-canvas px-1.5 py-0.5 text-xs text-muted"
                            >{{ shortcut.keys }}</kbd
                        >
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
