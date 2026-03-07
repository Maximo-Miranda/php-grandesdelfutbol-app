<?php

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

test('verify code marks email as verified', function () {
    $user = User::factory()->unverified()->create();
    Cache::put("verification-code:{$user->id}", '123456', now()->addMinutes(10));

    $this->actingAs($user)
        ->post(route('verification.verify-code'), ['code' => '123456'])
        ->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect(Cache::has("verification-code:{$user->id}"))->toBeFalse();
});

test('verify code rejects wrong code', function () {
    $user = User::factory()->unverified()->create();
    Cache::put("verification-code:{$user->id}", '123456', now()->addMinutes(10));

    $this->actingAs($user)
        ->post(route('verification.verify-code'), ['code' => '999999'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verify code rejects expired code', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.verify-code'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resend code sends new verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.resend-code'))
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, VerifyEmailCode::class);
});

test('already verified user is redirected on verify', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.verify-code'), ['code' => '123456'])
        ->assertRedirect();
});

test('already verified user is redirected on resend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.resend-code'))
        ->assertRedirect();
});
