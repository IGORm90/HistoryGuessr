<?php

use App\Models\User;

it('shows the admin login page to guests', function () {
    $this->get('/hstradmn/login')->assertOk();
});

it('redirects guests away from the dashboard', function () {
    $this->get('/hstradmn')->assertRedirect('/hstradmn/login');
});

it('lets admins and editors open the panel', function (string $state) {
    $user = $state === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($user)->get('/hstradmn')->assertOk();
})->with(['admin', 'editor']);
