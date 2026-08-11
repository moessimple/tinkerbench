<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import RunSnippetController from '@/actions/Application/Snippets/Controllers/RunSnippetController';

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

    <div class="mx-auto flex max-w-3xl flex-col gap-4 p-6">
        <textarea
            v-model="http.code"
            rows="10"
            placeholder="echo 'hello world';"
            class="w-full rounded border border-gray-300 p-3 font-mono text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        />

        <button
            type="button"
            :disabled="http.processing"
            class="self-start rounded bg-[#1b1b18] px-4 py-2 text-sm text-white disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
            @click="run"
        >
            {{ http.processing ? 'Running…' : 'Run' }}
        </button>

        <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400">
            {{ errorMessage }}
        </p>

        <pre
            class="min-h-16 rounded border border-gray-300 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            >{{ output }}</pre>
    </div>
</template>
