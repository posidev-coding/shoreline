<?php

use App\Models\User;

it('lets super admins and owners into the admin panel', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin')
        ->assertOk();
})->with(['superAdmin', 'owner']);

it('keeps clients out of the admin panel', function () {
    $this->actingAs(User::factory()->create())
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
