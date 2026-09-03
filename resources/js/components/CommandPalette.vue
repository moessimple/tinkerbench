<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import type { ComponentPublicInstance } from 'vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import CreateSnippetController from '@/actions/App/Http/Controllers/CreateSnippetController';
import DeleteSnippetController from '@/actions/App/Http/Controllers/DeleteSnippetController';
import ListProjectsController from '@/actions/App/Http/Controllers/ListProjectsController';
import ListSnippetsController from '@/actions/App/Http/Controllers/ListSnippetsController';
import OpenSnippetController from '@/actions/App/Http/Controllers/OpenSnippetController';
import UpdateSnippetNameController from '@/actions/App/Http/Controllers/UpdateSnippetNameController';
import { xsrfHeader } from '@/lib/csrf';
import { shortcuts } from '@/lib/shortcuts';

const props = defineProps<{ currentProject: string; currentSnippet: string }>();

const browseShortcut = shortcuts.find(
    (shortcut) => shortcut.id === 'browse',
)?.keys;

const isOpen = ref(false);
const names = ref<string[]>([]);
const projectNames = ref<string[]>([]);
const errorMessage = ref('');
const projectsErrorMessage = ref('');
const highlightedIndex = ref(0);
const createInputEl = useTemplateRef<HTMLInputElement>('createInput');

const createError = ref('');

const renaming = ref<string | null>(null);
const renameValue = ref('');
const renameError = ref('');
const renameSubmitting = ref(false);
const renameInputEl = ref<HTMLInputElement | null>(null);

// A plain `ref="renameInput"` inside v-for would resolve to an array of
// elements instead of a single one, even though only one row is ever
// rendered with the input at a time; a function ref sidesteps that.
function setRenameInputEl(el: Element | ComponentPublicInstance | null): void {
    renameInputEl.value = el as HTMLInputElement | null;
}

const deleting = ref<string | null>(null);
const deleteError = ref('');
const deleteSubmitting = ref(false);
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
}).withPrecognition('post', CreateSnippetController.url(props.currentProject));

// Both snippets and projects show at once by default, grouped into labeled
// sections like GitHub's own command palette (`Pages`/`Repositories`), so a
// project is discoverable without first knowing `/` exists. A leading `/`
// narrows to just projects, a leading `#` narrows to just snippets; neither
// is required to see the other category.
const scope = computed<'all' | 'projects' | 'snippets'>(() => {
    if (createForm.name.startsWith('/')) {
        return 'projects';
    }

    if (createForm.name.startsWith('#')) {
        return 'snippets';
    }

    return 'all';
});

const filterText = computed(() => {
    const raw =
        scope.value === 'all' ? createForm.name : createForm.name.slice(1);

    return raw.trim().toLowerCase();
});

// The `#` that narrows to the snippets section is part of createForm.name (it's
// bound straight to the input), so it has to be stripped before the name reaches
// the server, both for live Precognition validation and for the actual create.
function snippetNameFromInput(name: string): string {
    return scope.value === 'snippets' ? name.slice(1) : name;
}

createForm.transform((data) => ({
    ...data,
    name: snippetNameFromInput(data.name),
}));

const visibleSnippetNames = computed(() => {
    if (scope.value === 'projects') {
        return [];
    }

    return names.value.filter((name) =>
        name.toLowerCase().includes(filterText.value),
    );
});

const visibleProjectNames = computed(() => {
    if (scope.value === 'snippets') {
        return [];
    }

    return projectNames.value.filter((name) =>
        name.toLowerCase().includes(filterText.value),
    );
});

// Projects render after every snippet row, so a project row's position in the shared,
// continuous activeEntries list is offset by however many snippet rows come before it.
function projectEntryIndex(index: number): number {
    return visibleSnippetNames.value.length + index;
}

type PaletteEntry = { type: 'snippet' | 'project'; name: string };

// One continuous, keyboard-navigable list spanning both sections, snippets
// first then projects, in the same order they're rendered in the template.
const activeEntries = computed<PaletteEntry[]>(() => [
    ...visibleSnippetNames.value.map((name): PaletteEntry => ({
        type: 'snippet',
        name,
    })),
    ...visibleProjectNames.value.map((name): PaletteEntry => ({
        type: 'project',
        name,
    })),
]);

// Generic by default, same reasoning as GitHub's own "Search or jump to…":
// the field searches and jumps to either a snippet or a project, so wording
// it as if it only ever creates a new snippet would be misleading now that
// both sections show at once.
const inputLabel = computed(() => {
    if (scope.value === 'projects') {
        return 'Switch to project';
    }

    if (scope.value === 'snippets') {
        return 'Search snippets';
    }

    return 'Search or jump to';
});

const placeholder = computed(() => `${inputLabel.value}…`);

const dialogLabel = computed(() => {
    if (scope.value === 'projects') {
        return 'Projects';
    }

    if (scope.value === 'snippets') {
        return 'Snippets';
    }

    return 'Snippets and projects';
});

const activeOptionId = computed(() => {
    const entry = activeEntries.value[highlightedIndex.value];

    return entry ? `command-option-${entry.type}-${entry.name}` : undefined;
});

// Resets the highlight to the top of each new set of matches, same as VS Code
// Quick Open and GitHub's own command palette do as you narrow a search.
watch(
    () => createForm.name,
    () => {
        highlightedIndex.value = 0;
        createError.value = '';
    },
);

// The open dialog is a fixed, full-viewport backdrop that renders after both scope
// icons in the DOM, so it visually covers them the moment it's open; a click on
// either icon can never reach the button underneath, only the backdrop (which closes
// it). Toggle-closed is therefore the only reachable behavior for a second click,
// same as before there were two icons.
async function open(prefix: '' | '#' | '/'): Promise<void> {
    if (isOpen.value) {
        close();

        return;
    }

    isOpen.value = true;
    createForm.reset();
    createForm.name = prefix;
    createError.value = '';
    await Promise.all([loadNames(), loadProjects()]);
    await nextTick();
    createInputEl.value?.focus();
}

function toggle(): Promise<void> {
    return open('');
}

function openScoped(prefix: '#' | '/'): Promise<void> {
    return open(prefix);
}

function close(): void {
    isOpen.value = false;
    cancelRename();
    cancelDelete();
}

function moveHighlight(delta: number): void {
    if (activeEntries.value.length === 0) {
        return;
    }

    highlightedIndex.value =
        (highlightedIndex.value + delta + activeEntries.value.length) %
        activeEntries.value.length;
}

function highlight(index: number): void {
    highlightedIndex.value = index;
}

function selectHighlighted(): void {
    const entry = activeEntries.value[highlightedIndex.value];

    if (!entry) {
        return;
    }

    if (entry.type === 'project') {
        switchProject(entry.name);

        return;
    }

    openSnippet(entry.name);
}

function onSubmit(): void {
    if (activeEntries.value.length > 0) {
        selectHighlighted();

        return;
    }

    // Unlike snippets, there is no action to create a new Herd project from
    // here; typing a project name with no match simply goes nowhere.
    if (scope.value !== 'projects') {
        createSnippet();
    }
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (event.repeat) {
        return;
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'p') {
        event.preventDefault();
        void toggle();
    }
}

// Monaco's own keybinding service intercepts and stops most keydown events while
// the editor has focus, whether or not a custom action is registered for that
// key; capturing before that dispatch is the only way this still fires with the
// editor focused, not just elsewhere on the page.
onMounted(() =>
    window.addEventListener('keydown', onGlobalKeydown, { capture: true }),
);
onBeforeUnmount(() =>
    window.removeEventListener('keydown', onGlobalKeydown, { capture: true }),
);

async function loadNames(): Promise<void> {
    errorMessage.value = '';

    try {
        const response = await fetch(
            ListSnippetsController.url(props.currentProject),
        );

        if (!response.ok) {
            throw new Error('Unable to load snippets.');
        }

        names.value = (await response.json()) as string[];

        const currentIndex = visibleSnippetNames.value.indexOf(
            props.currentSnippet,
        );
        highlightedIndex.value = currentIndex === -1 ? 0 : currentIndex;
    } catch {
        names.value = [];
        errorMessage.value = 'Unable to load snippets.';
    }
}

async function loadProjects(): Promise<void> {
    projectsErrorMessage.value = '';

    try {
        const response = await fetch(ListProjectsController.url());

        if (!response.ok) {
            throw new Error('Unable to load projects.');
        }

        projectNames.value = (await response.json()) as string[];
    } catch {
        projectNames.value = [];
        projectsErrorMessage.value = 'Unable to load projects.';
    }
}

function openSnippet(name: string): void {
    isOpen.value = false;
    router.get(OpenSnippetController.url([props.currentProject, name]));
}

// Both rename and delete need the list to reflect their outcome: navigate away if the
// mutated snippet was the one currently open (its name/URL no longer applies), otherwise
// just refresh the list in place.
async function afterMutatingSnippet(
    name: string,
    whenCurrent: () => void,
): Promise<void> {
    if (props.currentSnippet === name) {
        whenCurrent();

        return;
    }

    await loadNames();
}

function switchProject(name: string): void {
    isOpen.value = false;
    router.get(OpenSnippetController.url({ project: name }));
}

function createSnippet(): void {
    createError.value = '';

    createForm.post(CreateSnippetController.url(props.currentProject), {
        onSuccess: () => {
            const name = snippetNameFromInput(createForm.name);
            createForm.reset();
            openSnippet(name);
        },
        // A taken name (409) or a write failure (500) is a domain error, not a
        // field-validation error, so it arrives here rather than in createForm.errors.
        onHttpException: (response) => {
            createError.value =
                (response.data as { message?: string })?.message ??
                'Unable to create the snippet.';
        },
    });
}

// Both a validation failure (Laravel's { message, errors }) and a domain-level
// failure ({ message } from abort()) carry a top-level message; field-specific
// validation messages take precedence when present.
async function errorMessageFrom(response: Response): Promise<string> {
    const body = (await response.json()) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    return body.errors
        ? Object.values(body.errors).flat().join(' ')
        : (body.message ?? 'Request failed');
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
    // Disabling the rename input mid-submit (see renameSubmitting below) blurs it, since a
    // browser always blurs an input that becomes disabled while focused; without this guard
    // that blur would fire this handler and hide the row while its own request is still
    // in flight.
    if (renameSubmitting.value) {
        return;
    }

    // Escape leaves the rename input focused at this point; a blur to somewhere else does not.
    // Only the Escape case needs the search input refocused, so the palette keeps handling keys
    // instead of dropping focus to <body>. A blur means the user already moved focus themselves.
    const escapedFromInput = renameInputEl.value === document.activeElement;

    renaming.value = null;
    renameError.value = '';

    if (escapedFromInput && isOpen.value) {
        void nextTick(() => createInputEl.value?.focus());
    }
}

async function confirmRename(name: string): Promise<void> {
    const newSnippetName = renameValue.value.trim();

    if (!newSnippetName || newSnippetName === name) {
        cancelRename();

        return;
    }

    renameSubmitting.value = true;

    try {
        const response = await fetch(
            UpdateSnippetNameController.url([props.currentProject, name]),
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    ...xsrfHeader(),
                },
                body: JSON.stringify({ name: newSnippetName }),
            },
        );

        if (!response.ok) {
            renameError.value = await errorMessageFrom(response);

            return;
        }

        renaming.value = null;

        await afterMutatingSnippet(name, () => openSnippet(newSnippetName));
    } finally {
        renameSubmitting.value = false;
    }
}

async function startDelete(name: string): Promise<void> {
    deleting.value = name;
    deleteError.value = '';
    await nextTick();
    cancelDeleteButtonEl.value?.focus();
}

function cancelDelete(): void {
    // The confirm row's buttons had focus; returning it to the search input keeps the palette
    // handling keys. Skipped when close() is the caller, since it has already torn the palette down.
    const wasOpen = isOpen.value;

    deleting.value = null;
    deleteError.value = '';

    if (wasOpen) {
        void nextTick(() => createInputEl.value?.focus());
    }
}

async function confirmDelete(name: string): Promise<void> {
    deleteSubmitting.value = true;

    try {
        const response = await fetch(
            DeleteSnippetController.url([props.currentProject, name]),
            {
                method: 'DELETE',
                headers: xsrfHeader(),
            },
        );

        if (!response.ok) {
            deleteError.value = await errorMessageFrom(response);

            return;
        }

        deleting.value = null;

        await afterMutatingSnippet(name, () =>
            router.get(
                OpenSnippetController.url({ project: props.currentProject }),
            ),
        );
    } finally {
        deleteSubmitting.value = false;
    }
}
</script>

<template>
    <div class="relative flex flex-col items-center gap-1">
        <button
            type="button"
            title="Switch project"
            aria-label="Switch project"
            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
            @click="openScoped('/')"
        >
            <svg
                viewBox="0 0 16 16"
                width="16"
                height="16"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    d="M1.75 1A1.75 1.75 0 0 0 0 2.75v10.5C0 14.216.784 15 1.75 15h12.5A1.75 1.75 0 0 0 16 13.25v-8.5A1.75 1.75 0 0 0 14.25 3H7.5a.25.25 0 0 1-.2-.1l-.9-1.2C6.07 1.26 5.55 1 5 1H1.75Z"
                />
            </svg>
        </button>
        <button
            type="button"
            :title="`Browse snippets (${browseShortcut})`"
            aria-label="Browse snippets"
            class="flex h-8 w-8 items-center justify-center rounded text-muted hover:bg-line/30 hover:text-fg"
            @click="openScoped('#')"
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
                    d="M11.5 7a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Zm-.82 4.74a6 6 0 1 1 1.06-1.06l3.04 3.04a.75.75 0 1 1-1.06 1.06l-3.04-3.04Z"
                />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="fixed inset-0 z-20 flex justify-center bg-black/50 pt-[15vh]"
            @click.self="close"
        >
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="dialogLabel"
                class="h-fit w-full max-w-xs rounded-md border border-line bg-surface shadow-2xl"
            >
                <form
                    class="border-b border-line p-2"
                    @submit.prevent="onSubmit"
                >
                    <input
                        ref="createInput"
                        v-model="createForm.name"
                        type="text"
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="command-listbox"
                        :aria-activedescendant="activeOptionId"
                        :aria-label="inputLabel"
                        :placeholder="placeholder"
                        :disabled="createForm.processing"
                        class="w-full rounded border border-line bg-transparent px-2 py-1 font-mono text-sm text-fg placeholder:text-muted focus:outline-none"
                        @change="createForm.validate('name')"
                        @keydown.down.prevent="moveHighlight(1)"
                        @keydown.up.prevent="moveHighlight(-1)"
                        @keydown.escape="close"
                    />
                    <p
                        v-if="
                            scope !== 'projects' &&
                            visibleSnippetNames.length === 0 &&
                            createForm.invalid('name')
                        "
                        class="mt-1 font-mono text-xs text-red-400"
                    >
                        {{ createForm.errors.name }}
                    </p>
                    <p
                        v-if="createError"
                        class="mt-1 font-mono text-xs text-red-400"
                    >
                        {{ createError }}
                    </p>
                </form>

                <template v-if="errorMessage || projectsErrorMessage">
                    <p
                        v-if="errorMessage"
                        class="px-3 py-2 font-mono text-xs text-red-400"
                    >
                        {{ errorMessage }}
                    </p>
                    <p
                        v-if="projectsErrorMessage"
                        class="px-3 py-2 font-mono text-xs text-red-400"
                    >
                        {{ projectsErrorMessage }}
                    </p>
                </template>
                <template v-else-if="activeEntries.length === 0">
                    <p
                        v-if="scope === 'projects'"
                        class="px-3 py-2 font-mono text-xs text-muted"
                    >
                        No projects found.
                    </p>
                    <p
                        v-else-if="filterText === '' && names.length === 0"
                        class="px-3 py-2 font-mono text-xs text-muted"
                    >
                        No snippets found.
                    </p>
                    <p v-else class="px-3 py-2 font-mono text-xs text-muted">
                        No snippet named "{{
                            snippetNameFromInput(createForm.name).trim()
                        }}" yet. Press Enter to create it.
                    </p>
                </template>

                <ul
                    v-else
                    id="command-listbox"
                    role="listbox"
                    class="max-h-64 overflow-auto py-1"
                >
                    <template v-if="visibleSnippetNames.length > 0">
                        <li
                            role="presentation"
                            class="px-3 pt-2 pb-1 font-mono text-[10px] font-semibold tracking-widest text-muted uppercase"
                        >
                            Snippets
                        </li>
                        <li
                            v-for="(name, index) in visibleSnippetNames"
                            :id="`command-option-snippet-${name}`"
                            :key="`snippet-${name}`"
                            role="option"
                            :aria-selected="index === highlightedIndex"
                            class="group flex flex-col gap-1 px-3 py-1.5 font-mono text-sm text-fg hover:bg-line/30"
                            :class="{
                                'bg-line/60': index === highlightedIndex,
                            }"
                            @mouseenter="highlight(index)"
                        >
                            <input
                                v-if="renaming === name"
                                :ref="setRenameInputEl"
                                v-model="renameValue"
                                type="text"
                                :aria-label="`Rename ${name}`"
                                :disabled="renameSubmitting"
                                class="w-full rounded border border-line bg-transparent px-2 py-0.5 font-mono text-sm text-fg focus:outline-none"
                                @keydown.enter.prevent="confirmRename(name)"
                                @keydown.escape="cancelRename"
                                @blur="cancelRename"
                            />
                            <div
                                v-else-if="deleting === name"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-muted"
                                    >Delete '{{ name }}'?</span
                                >
                                <span class="flex shrink-0 items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="deleteSubmitting"
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
                            <div
                                v-else
                                class="flex items-center justify-between gap-2"
                            >
                                <button
                                    type="button"
                                    class="flex flex-1 items-center gap-1.5 truncate text-left"
                                    @click="openSnippet(name)"
                                >
                                    <span
                                        v-if="name === currentSnippet"
                                        class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{ name }}</span>
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
                    </template>

                    <template v-if="visibleProjectNames.length > 0">
                        <li
                            role="presentation"
                            class="px-3 pt-2 pb-1 font-mono text-[10px] font-semibold tracking-widest text-muted uppercase"
                        >
                            Projects
                        </li>
                        <li
                            v-for="(name, index) in visibleProjectNames"
                            :id="`command-option-project-${name}`"
                            :key="`project-${name}`"
                            role="option"
                            :aria-selected="
                                projectEntryIndex(index) === highlightedIndex
                            "
                            class="flex flex-col gap-1 px-3 py-1.5 font-mono text-sm text-fg hover:bg-line/30"
                            :class="{
                                'bg-line/60':
                                    projectEntryIndex(index) ===
                                    highlightedIndex,
                            }"
                            @mouseenter="highlight(projectEntryIndex(index))"
                        >
                            <button
                                type="button"
                                class="flex flex-1 items-center gap-1.5 truncate text-left"
                                @click="switchProject(name)"
                            >
                                <span
                                    v-if="name === currentProject"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ name }}</span>
                            </button>
                        </li>
                    </template>
                </ul>

                <p
                    class="border-t border-line px-3 py-1.5 font-mono text-[11px] text-muted"
                >
                    Type <kbd class="text-fg">#</kbd> for snippets,
                    <kbd class="text-fg">/</kbd> for projects.
                </p>
            </div>
        </div>
    </div>
</template>
