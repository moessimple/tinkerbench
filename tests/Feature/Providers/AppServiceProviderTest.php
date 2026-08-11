<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\Password;

test('enforces a strong password policy in production', function (): void {
    app()->instance('env', 'production');

    $validator = validator(['password' => 'password'], ['password' => Password::default()]);
    expect($validator->fails())->toBeTrue();

    $validator = validator(['password' => 'Str0ng!Passw0rd#2024'], ['password' => Password::default()]);
    expect($validator->fails())->toBeFalse();

    app()->instance('env', 'testing');
});

test('allows the default password policy outside production', function (): void {
    expect(app()->isProduction())->toBeFalse();

    $validator = validator(['password' => 'password'], ['password' => Password::default()]);
    expect($validator->fails())->toBeFalse();
});
