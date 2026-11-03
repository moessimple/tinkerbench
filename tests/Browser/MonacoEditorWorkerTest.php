<?php

declare(strict_types=1);

it('starts the bundled Monaco worker and receives its initialization reply', function (): void {
    $page = visitEditor();

    $page->script(<<<'JAVASCRIPT'
        window.__editorWorkerReady = false;
        const worker = window.MonacoEnvironment.getWorker('', 'editorWorkerService');
        worker.onmessage = ({ data }) => {
            if (data.type === 1 && data.seq === '1') {
                window.__editorWorkerReady = data.vsWorker === 1 && !data.err;
                worker.terminate();
            }
        };
        worker.onerror = () => worker.terminate();

        // Monaco's first message boots the worker; the next performs its RPC handshake.
        worker.postMessage('editorWorkerService');
        worker.postMessage({
            vsWorker: 1,
            type: 0,
            req: '1',
            channel: 'default',
            method: '$initialize',
            args: [1],
        });
        JAVASCRIPT);

    $page->assertScript('window.__editorWorkerReady')
        ->assertNoJavaScriptErrors();
});
