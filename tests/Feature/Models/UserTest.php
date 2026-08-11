<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

test('casts email_verified_at to a CarbonImmutable instance', function (): void {
    $user = User::factory()->create();

    expect($user->email_verified_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('hashes the password on assignment', function (): void {
    $user = User::factory()->create(['password' => 'plain-text-password']);

    expect($user->password)
        ->not->toBe('plain-text-password')
        ->and(Hash::check('plain-text-password', $user->password))->toBeTrue();
});
