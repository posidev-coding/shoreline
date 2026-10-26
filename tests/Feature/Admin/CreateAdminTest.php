<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('creates a super admin and emails a link to set a password', function () {
    Notification::fake();

    $this->artisan('app:create-admin', ['email' => 'Taylor@Example.com', '--name' => 'Taylor'])
        ->expectsOutputToContain('emailed a link to set a password')
        ->assertSuccessful();

    $user = User::query()->where('email', 'taylor@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->name)->toBe('Taylor')
        ->and($user->app_authentication_secret)->toBeNull();
    Notification::assertSentTo($user, ResetPassword::class, fn (ResetPassword $notification) => str_contains($notification->url, '/admin/password-reset/reset'));
});

it('creates the owner with --owner', function () {
    Notification::fake();

    $this->artisan('app:create-admin', ['email' => 'owner@example.com', '--owner' => true])->assertSuccessful();

    expect(User::query()->where('email', 'owner@example.com')->value('role'))->toBe(UserRole::Owner);
});

it('refuses an existing account or an invalid email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('app:create-admin', ['email' => 'taken@example.com'])->assertFailed();
    $this->artisan('app:create-admin', ['email' => 'not-an-email'])->assertFailed();
});
