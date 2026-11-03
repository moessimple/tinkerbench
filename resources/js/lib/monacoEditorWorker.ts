import editorWorkerUrl from 'monaco-editor/editor/editor.worker?worker&url';

/**
 * Starts the worker from a same-origin blob that imports the real worker script. In dev, Vite serves
 * that script from its own origin (http://[::1]:5173) and browsers refuse `new Worker()` for a
 * cross-origin URL, while a module import inside the worker only needs CORS, which Vite allows.
 */
export function createEditorWorker(): Worker {
    const workerUrl = new URL(editorWorkerUrl, window.location.href).href;
    const bootstrap = new Blob([`import ${JSON.stringify(workerUrl)};`], {
        type: 'text/javascript',
    });

    return new Worker(URL.createObjectURL(bootstrap), { type: 'module' });
}
