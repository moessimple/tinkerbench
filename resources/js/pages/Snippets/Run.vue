<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import RunSnippetController from '@/actions/Application/Snippets/Controllers/RunSnippetController';
import MonacoEditor from '@/components/MonacoEditor.vue';

defineProps<{ laravelVersion: string; phpVersion: string }>();

const output = ref('');
const errorMessage = ref('');

const http = useHttp<{ code: string }, { output: string }>({
    code: '',
});

function run(): void {
    errorMessage.value = '';

    http.post(RunSnippetController.url(), {
        onSuccess: (data) => {
            output.value = data.output;
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors).join(' ');
        },
        onHttpException: (response) => {
            errorMessage.value = `Request failed (${response.status}).`;
        },
    });
}
</script>

<template>
    <Head title="Snippets" />

    <div class="flex min-h-screen flex-col bg-canvas text-fg">
        <div class="mx-auto shrink-0 px-4 pt-10 pb-8 text-center">
            <div
                class="mb-6 flex items-center justify-center gap-2 font-mono text-xs font-semibold tracking-widest text-muted uppercase"
            >
                <svg
                    viewBox="0 0 16 16"
                    width="14"
                    height="14"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="text-accent"
                    aria-hidden="true"
                >
                    <path d="M5.5 5.2c0-1.8 1.1-3.2 2.5-3.2s2.5 1.4 2.5 3.2" />
                    <path
                        d="M3.8 5.2h8.4l-1.3 7.4a1 1 0 0 1-1 .8H6.1a1 1 0 0 1-1-.8L3.8 5.2Z"
                    />
                </svg>
                tinkerbench
            </div>
            <p class="font-mono text-xs text-muted">
                PHP {{ phpVersion }} · Laravel {{ laravelVersion }}
            </p>
        </div>

        <div
            class="mx-auto mb-6 flex w-full max-w-358 flex-1 flex-col gap-4 px-4"
        >
            <div
                class="flex flex-col overflow-hidden rounded-md border border-line bg-surface min-[900px]:flex-row"
            >
                <div
                    class="flex min-w-0 flex-3 flex-col border-b border-line min-[900px]:border-r min-[900px]:border-b-0"
                >
                    <div
                        class="flex items-center justify-between border-b border-line px-3 py-2"
                    >
                        <span
                            class="font-mono text-xs font-semibold tracking-widest text-muted uppercase"
                        >
                            Snippet
                        </span>
                        <button
                            type="button"
                            :disabled="http.processing"
                            class="rounded bg-accent px-3 py-1 text-sm font-medium text-canvas disabled:opacity-50"
                            @click="run"
                        >
                            {{ http.processing ? 'Running…' : 'Run' }}
                        </button>
                    </div>
                    <div class="h-96">
                        <MonacoEditor
                            :initial-value="http.code"
                            @change="(content) => (http.code = content)"
                        />
                    </div>
                </div>

                <div class="flex min-h-0 min-w-0 flex-2 flex-col">
                    <div
                        class="border-b border-line px-3 py-2 font-mono text-xs font-semibold tracking-widest text-muted uppercase"
                    >
                        Output
                    </div>
                    <pre
                        class="h-96 flex-1 overflow-auto p-4 font-mono text-sm whitespace-pre-wrap"
                        >{{ output }}</pre>
                </div>
            </div>

            <p v-if="errorMessage" class="font-mono text-sm text-red-400">
                {{ errorMessage }}
            </p>
        </div>
    </div>
</template>
