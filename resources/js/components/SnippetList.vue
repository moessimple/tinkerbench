<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import type { ComponentPublicInstance } from 'vue';
import { nextTick, ref } from 'vue';
import CreateSnippetController from '@/actions/Application/Snippets/Controllers/CreateSnippetController';
import DeleteSnippetController from '@/actions/Application/Snippets/Controllers/DeleteSnippetController';
import ListSnippetsController from '@/actions/Application/Snippets/Controllers/ListSnippetsController';
import OpenSnippetController from '@/actions/Application/Snippets/Controllers/OpenSnippetController';
import UpdateSnippetNameController from '@/actions/Application/Snippets/Controllers/UpdateSnippetNameController';
import { xsrfHeader } from '@/lib/csrf';

const props = defineProps<{ currentSnippet: string }>();

const isOpen = ref(false);
const names = ref<string[]>([]);
const errorMessage = ref('');

const renaming = ref<string | null>(null);
const renameValue = ref('');
const renameError = ref('');
const renameInputEl = ref<HTMLInputElement | null>(null);

// A plain `ref="renameInput"` inside v-for would resolve to an array of
// elements instead of a single one, even though only one row is ever
// rendered with the input at a time; a function ref sidesteps that.
function setRenameInputEl(el: Element | ComponentPublicInstance | null): void {
    renameInputEl.value = el as HTMLInputElement | null;
}

const deleting = ref<string | null>(null);
const deleteError = ref('');
const cancelDeleteButtonEl = ref<HTMLButtonElement | null>(null);

function setCancelDeleteButtonEl(
    el: Element | ComponentPublicInstance | null,
): void {
    cancelDeleteButtonEl.value = el as HTMLButtonElement | null;
}

// Precognition validates against SnippetNameRequest's real rules on the server,
// so the character-set/length rule lives in exactly one place instead of being
// duplicated here.
const createForm = useHttp<{ name: string }, { ok: boolean }>({
    name: '',
}).withPrecognition('post', CreateSnippetController.url());

async function toggle(): Promise<void> {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await loadNames();
    }
}

defineExpose({ toggle });

async function loadNames(): Promise<void> {
    errorMessage.value = '';

    try {
        const response = await fetch(ListSnippetsController.url());

        if (!response.ok) {
            throw new Error('Unable to load snippets.');
        }

        names.value = (await response.json()) as string[];
    } catch {
        names.value = [];
        errorMessage.value = 'Unable to load snippets.';
    }
}

function openSnippet(name: string): void {
    isOpen.value = false;
    router.get(OpenSnippetController.url(name));
}

function createSnippet(): void {
    createForm.post(CreateSnippetController.url(), {
        onSuccess: () => {
            const name = createForm.name;
            createForm.reset();
            openSnippet(name);
        },
    });
}

// Laravel shapes a validation failure as { message, errors }, but the domain-level
// failures this project's own controllers return (rename conflicts, missing snippets)
// are shaped as { ok, error }; this reads whichever one the response actually has.
async function errorMessageFrom(response: Response): Promise<string> {
    const body = (await response.json()) as {
        error?: string;
        errors?: Record<string, string[]>;
    };

    return body.errors
        ? Object.values(body.errors).flat().join(' ')
        : (body.error ?? 'Request failed');
}

async function startRename(name: string): Promise<void> {
    renaming.value = name;
    renameValue.value = name;
    renameError.value = '';
    await nextTick();
    renameInputEl.value?.focus();
    renameInputEl.value?.select();
}

function cancelRename(): void {
    renaming.value = null;
    renameError.value = '';
}

async function confirmRename(name: string): Promise<void> {
    const newSnippetName = renameValue.value.trim();

    if (!newSnippetName || newSnippetName === name) {
        cancelRename();

        return;
    }

    const response = await fetch(UpdateSnippetNameController.url(name), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...xsrfHeader() },
        body: JSON.stringify({ name: newSnippetName }),
    });

    if (!response.ok) {
        renameError.value = await errorMessageFrom(response);

        return;
    }

    renaming.value = null;

    if (props.currentSnippet === name) {
        openSnippet(newSnippetName);

        return;
    }

    await loadNames();
}

async function startDelete(name: string): Promise<void> {
    deleting.value = name;
    deleteError.value = '';
    await nextTick();
    cancelDeleteButtonEl.value?.focus();
}

function cancelDelete(): void {
    deleting.value = null;
    deleteError.value = '';
}

async function confirmDelete(name: string): Promise<void> {
    const response = await fetch(DeleteSnippetController.url(name), {
        method: 'DELETE',
        headers: xsrfHeader(),
    });

    if (!response.ok) {
        deleteError.value = await errorMessageFrom(response);

        return;
    }

    deleting.value = null;

    if (props.currentSnippet === name) {
        // OpenSnippetController.url() with no snippet argument returns '' (a
        // wayfinder quirk stripping the route's trailing optional segment),
        // and router.get('') reloads the current URL instead of navigating
        // to the root, so the fallback route is spelled out explicitly.
        router.get('/');

        return;
    }

    await loadNames();
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            title="Browse snippets"
            aria-label="Browse snippets"
            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
            @click="toggle"
        >
            <svg
                viewBox="0 0 16 16"
                width="16"
                height="16"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M2 3.75A.75.75 0 0 1 2.75 3h10.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 3.75Zm0 4A.75.75 0 0 1 2.75 7h10.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 7.75Zm0 4A.75.75 0 0 1 2.75 11h10.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z"
                />
            </svg>
        </button>

        <div
            v-if="isOpen"
            role="dialog"
            aria-label="Snippets"
            class="absolute top-10 left-0 z-10 w-64 rounded-md border border-line bg-surface shadow-2xl"
        >
            <form
                class="border-b border-line p-2"
                @submit.prevent="createSnippet"
            >
                <input
                    v-model="createForm.name"
                    type="text"
                    aria-label="New snippet name"
                    placeholder="New snippet name…"
                    class="w-full rounded border border-line bg-transparent px-2 py-1 font-mono text-sm text-fg placeholder:text-muted focus:outline-none"
                    @change="createForm.validate('name')"
                />
                <p
                    v-if="createForm.invalid('name')"
                    class="mt-1 font-mono text-xs text-red-400"
                >
                    {{ createForm.errors.name }}
                </p>
            </form>

            <p
                v-if="errorMessage"
                class="px-3 py-2 font-mono text-xs text-red-400"
            >
                {{ errorMessage }}
            </p>
            <p
                v-else-if="names.length === 0"
                class="px-3 py-2 font-mono text-xs text-muted"
            >
                No snippets found.
            </p>

            <ul v-else class="max-h-64 overflow-auto py-1">
                <li
                    v-for="name in names"
                    :key="name"
                    class="group flex flex-col gap-1 px-3 py-1.5 font-mono text-sm text-fg hover:bg-line/30"
                    :class="{ 'bg-accent/15': name === currentSnippet }"
                >
                    <input
                        v-if="renaming === name"
                        :ref="setRenameInputEl"
                        v-model="renameValue"
                        type="text"
                        :aria-label="`Rename ${name}`"
                        class="w-full rounded border border-line bg-transparent px-2 py-0.5 font-mono text-sm text-fg focus:outline-none"
                        @keydown.enter.prevent="confirmRename(name)"
                        @keydown.escape="cancelRename"
                        @blur="cancelRename"
                    />
                    <div
                        v-else-if="deleting === name"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-muted">Delete '{{ name }}'?</span>
                        <span class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-line/50"
                                @click="confirmDelete(name)"
                                @keydown.escape="cancelDelete"
                            >
                                Yes
                            </button>
                            <button
                                type="button"
                                :ref="setCancelDeleteButtonEl"
                                class="rounded px-1.5 py-0.5 text-xs text-muted hover:bg-line/50"
                                @click="cancelDelete"
                                @keydown.escape="cancelDelete"
                            >
                                No
                            </button>
                        </span>
                    </div>
                    <div v-else class="flex items-center justify-between gap-2">
                        <button
                            type="button"
                            class="flex-1 truncate text-left"
                            @click="openSnippet(name)"
                        >
                            {{ name }}
                        </button>
                        <span
                            class="flex shrink-0 items-center gap-1 opacity-0 group-hover:opacity-100"
                        >
                            <button
                                type="button"
                                title="Rename snippet"
                                :aria-label="`Rename ${name}`"
                                class="flex h-5 w-5 items-center justify-center rounded text-muted hover:bg-line/50 hover:text-fg"
                                @click="startRename(name)"
                            >
                                <svg
                                    viewBox="0 0 16 16"
                                    width="12"
                                    height="12"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25a1.75 1.75 0 0 1 .445-.758l8.61-8.61Zm.176 4.823L9.75 4.81l-6.286 6.287a.25.25 0 0 0-.064.108l-.558 1.953 1.953-.558a.25.25 0 0 0 .108-.064Zm1.238-3.763a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354Z"
                                    />
                                </svg>
                            </button>
                            <button
                                type="button"
                                title="Delete snippet"
                                :aria-label="`Delete ${name}`"
                                class="flex h-5 w-5 items-center justify-center rounded text-muted hover:bg-line/50 hover:text-fg"
                                @click="startDelete(name)"
                            >
                                <svg
                                    viewBox="0 0 16 16"
                                    width="12"
                                    height="12"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M4 1.75V3H1.75a.75.75 0 0 0 0 1.5h.6l.63 9.44A2 2 0 0 0 4.98 16h6.04a2 2 0 0 0 1.99-1.86l.63-9.44h.6a.75.75 0 0 0 0-1.5H12V1.75A1.75 1.75 0 0 0 10.25 0h-4.5A1.75 1.75 0 0 0 4 1.75Zm1.5 0a.25.25 0 0 1 .25-.25h4.5a.25.25 0 0 1 .25.25V3h-5V1.75ZM4.5 4.5h7l-.62 9.32a.5.5 0 0 1-.5.43H5.62a.5.5 0 0 1-.5-.43L4.5 4.5Z"
                                    />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <p
                        v-if="renaming === name && renameError"
                        class="font-mono text-xs text-red-400"
                    >
                        {{ renameError }}
                    </p>
                    <p
                        v-if="deleting === name && deleteError"
                        class="font-mono text-xs text-red-400"
                    >
                        {{ deleteError }}
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>
