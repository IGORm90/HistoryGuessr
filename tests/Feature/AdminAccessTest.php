<?php

use App\Models\User;

it('shows the admin login page to guests', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets admins and editors open the panel', function (string $state) {
    $user = $state === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertOk();
})->with(['admin', 'editor']);
