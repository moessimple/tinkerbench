<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

it('casts email_verified_at to a Carbon instance', function (): void {
    $user = User::factory()->create();

    expect($user->email_verified_at)->toBeInstanceOf(CarbonInterface::class);
});

it('hashes the password', function (): void {
    $user = User::factory()->create(['password' => 'plain-text-password']);

    expect($user->password)->not->toBe('plain-text-password')
        ->and(Hash::check('plain-text-password', $user->password))->toBeTrue();
});
