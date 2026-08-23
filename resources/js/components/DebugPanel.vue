<script setup lang="ts">
import type { SnippetDebugPayload } from '@/types';

defineProps<{
    debug: SnippetDebugPayload;
}>();
</script>

<template>
    <div
        role="tabpanel"
        aria-label="Snippet debug"
        class="flex-1 overflow-auto p-4 font-mono text-base [font-variant-ligatures:none]"
    >
        <section v-if="debug.time" class="mt-6 first:mt-0">
            <h2
                class="mb-2 text-xs font-semibold tracking-widest text-muted uppercase"
            >
                Timing
            </h2>
            <ul class="flex flex-col gap-1">
                <li
                    v-for="(measure, index) in debug.time.measures"
                    :key="index"
                    class="flex justify-between gap-3"
                >
                    <span>{{ measure.label }}</span>
                    <span class="text-muted">{{ measure.duration_str }}</span>
                </li>
            </ul>
        </section>

        <section v-if="debug.memory" class="mt-6 first:mt-0">
            <h2
                class="mb-2 text-xs font-semibold tracking-widest text-muted uppercase"
            >
                Memory
            </h2>
            <p>{{ debug.memory.peak_usage_str }}</p>
        </section>

        <section
            v-if="debug.queries && debug.queries.count > 0"
            class="mt-6 first:mt-0"
        >
            <h2
                class="mb-2 text-xs font-semibold tracking-widest text-muted uppercase"
            >
                Queries ({{ debug.queries.count }})
            </h2>
            <table aria-label="Queries" class="w-full text-left">
                <thead>
                    <tr class="text-muted">
                        <th class="pr-3 pb-1 font-normal">SQL</th>
                        <th class="pr-3 pb-1 font-normal">Bindings</th>
                        <th class="pb-1 font-normal">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(statement, index) in debug.queries.statements"
                        :key="index"
                        class="border-t border-line align-top"
                    >
                        <td class="py-1 pr-3 break-all">{{ statement.sql }}</td>
                        <td class="py-1 pr-3 text-muted">
                            {{ statement.params.join(', ') }}
                        </td>
                        <td class="py-1 text-muted">
                            {{ statement.duration_str }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            v-if="debug.messages && debug.messages.count > 0"
            class="mt-6 first:mt-0"
        >
            <h2
                class="mb-2 text-xs font-semibold tracking-widest text-muted uppercase"
            >
                Dumps
            </h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="(message, index) in debug.messages.messages"
                    :key="index"
                    class="whitespace-pre-wrap"
                >
                    {{ message.message }}
                </li>
            </ul>
        </section>

        <section
            v-if="debug.exceptions && debug.exceptions.count > 0"
            class="mt-6 first:mt-0"
        >
            <h2
                class="mb-2 text-xs font-semibold tracking-widest text-red-400 uppercase"
            >
                Exceptions
            </h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="(exception, index) in debug.exceptions.exceptions"
                    :key="index"
                >
                    <p class="text-red-400">
                        {{ exception.type }}: {{ exception.message }}
                    </p>
                    <p class="text-muted">
                        {{ exception.file }}:{{ exception.line }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
