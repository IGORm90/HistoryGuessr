<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

it('lets editors manage content but not users', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/questions')->assertOk();
    $this->get('/admin/quizzes')->assertOk();
    $this->get('/admin/users')->assertForbidden();
});

it('lets admins manage users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get('/admin/users')->assertOk();
    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$admin]);
});

it('does not let admins delete themselves', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('delete', User::factory()->create()))->toBeTrue();
});

it('loads the location picker script in the panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/questions/create')
        ->assertOk()
        ->assertSee('mapLocationPicker', escape: false)
        ->assertSee('location-picker', escape: false);
});
