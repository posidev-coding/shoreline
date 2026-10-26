<?php

use App\Models\User;

it('lets super admins and owners with MFA into the admin panel', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->withMfa()->create())
        ->get('/admin')
        ->assertOk();
})->with(['superAdmin', 'owner']);

it('sends admins without MFA to set it up before anything else', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get('/admin')
        ->assertRedirect('/admin/multi-factor-authentication/set-up');
})->with(['superAdmin', 'owner']);

it('keeps clients out of the admin panel', function () {
    $this->actingAs(User::factory()->withMfa()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('makes new users clients and stores emails lowercase', function () {
    $user = User::factory()->create(['email' => '  Owner@Example.COM ']);

    expect($user->refresh())
        ->role->value->toBe('client')
        ->email->toBe('owner@example.com');
});

it('never mass-assigns a role', function () {
    $user = User::query()->create(['name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'secret', 'role' => 'super_admin']);

    expect($user->refresh()->role->value)->toBe('client');
});
