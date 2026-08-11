<?php

declare(strict_types=1);

test('runs the posted code isolated and returns its output as json', function (): void {
    $this->postJson('/snippets/executions', ['code' => 'echo 1 + 1;'])
        ->assertOk()
        ->assertExactJson(['output' => '2']);
});

test('requires code', function (): void {
    $this->postJson('/snippets/executions', [])
        ->assertInvalid(['code']);
});
