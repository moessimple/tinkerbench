import { reactive, watch } from 'vue';

/** Ids of the "default off" watchers a project can opt into per run. */
export const OPTIONAL_WATCHERS: { id: string; label: string }[] = [
    { id: 'view', label: 'Views' },
];

function storageKey(project: string): string {
    return `watcher-toggles:${project}`;
}

function readInitialToggles(project: string): Record<string, boolean> {
    const stored = localStorage.getItem(storageKey(project));

    if (!stored) {
        return {};
    }

    try {
        const parsed: unknown = JSON.parse(stored);

        return parsed !== null && typeof parsed === 'object'
            ? (parsed as Record<string, boolean>)
            : {};
    } catch {
        return {};
    }
}

/** Per-project, localStorage-backed watcher toggle state. Default: every watcher off. */
export function useWatcherToggles(project: string) {
    const toggles = reactive<Record<string, boolean>>(
        readInitialToggles(project),
    );

    watch(
        toggles,
        () => {
            localStorage.setItem(storageKey(project), JSON.stringify(toggles));
        },
        { deep: true, flush: 'sync' },
    );

    function isEnabled(id: string): boolean {
        return toggles[id] ?? false;
    }

    function toggle(id: string): void {
        toggles[id] = !isEnabled(id);
    }

    return { toggles, isEnabled, toggle };
}
