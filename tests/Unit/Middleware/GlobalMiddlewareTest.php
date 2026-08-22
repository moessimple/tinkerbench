<?php

declare(strict_types=1);

it('rejects a non-loopback request', function (string $method, string $uri): void {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->call($method, $uri)
        ->assertForbidden();
})->with([
    'run snippet' => ['POST', 'projects/my-project/snippets/executions'],
    'list projects' => ['GET', 'api/projects'],
    'start language server' => ['POST', 'api/projects/my-project/language-server'],
    'list snippets' => ['GET', 'api/projects/my-project/snippets'],
    'create snippet' => ['POST', 'api/projects/my-project/snippets'],
    'update snippet content' => ['PUT', 'api/projects/my-project/snippets/my-snippet'],
    'rename snippet' => ['PATCH', 'api/projects/my-project/snippets/my-snippet'],
    'delete snippet' => ['DELETE', 'api/projects/my-project/snippets/my-snippet'],
    'open snippet' => ['GET', 'my-project/my-snippet'],
]);
