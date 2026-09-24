import { reactive, watch } from 'vue';

/**
 * Every watcher a run can register, each with its own default, like debugbar's `collectors` map.
 * Views are off by default: nested component renders would add noise to every run.
 */
export const WATCHERS: {
    id: string;
    label: string;
    enabledByDefault: boolean;
}[] = [
    { id: 'dump', label: 'Dumps', enabledByDefault: true },
    { id: 'query', label: 'Queries', enabledByDefault: true },
    { id: 'log', label: 'Logs', enabledByDefault: true },
    { id: 'n_plus_one', label: 'N+1', enabledByDefault: true },
    { id: 'view', label: 'Views', enabledByDefault: false },
    { id: 'http_client', label: 'HTTP', enabledByDefault: true },
];

/** The watchers whose time the timeline splits out of the application time. */
export type TimedKind = 'http_client' | 'query';

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

/** Per-project, localStorage-backed watcher toggle state. Unset watchers use their default. */
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
        return (
            toggles[id] ??
            WATCHERS.some(
                (watcher) => watcher.id === id && watcher.enabledByDefault,
            )
        );
    }

    function toggle(id: string): void {
        toggles[id] = !isEnabled(id);
    }

    return { toggles, isEnabled, toggle };
}
