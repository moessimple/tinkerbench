import EditorWorker from 'monaco-editor/editor/editor.worker?worker';

export function createEditorWorker(): Worker {
    return new EditorWorker();
}
