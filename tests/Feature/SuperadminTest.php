<?php

use App\Models\User;
use App\Models\PremiumServiceOrder;

test('unauthenticated users are redirected from superadmin routes', function () {
    $this->get('/superadmin')->assertRedirect('/login');
    $this->patch('/superadmin/users/1/permissions', [])->assertRedirect('/login');
    $this->patch('/superadmin/users/1/toggle-superadmin', [])->assertRedirect('/login');
});

test('regular users cannot access superadmin routes', function () {
    $user = User::factory()->create(['is_superadmin' => false]);

    $this->actingAs($user)->get('/superadmin')->assertStatus(403);
    $this->actingAs($user)->patch("/superadmin/users/{$user->id}/permissions", [])->assertStatus(403);
    $this->actingAs($user)->patch("/superadmin/users/{$user->id}/toggle-superadmin", [])->assertStatus(403);
});

test('superadmin can access superadmin dashboard', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);

    $response = $this->actingAs($admin)->get('/superadmin');

    $response->assertStatus(200);
});

test('superadmin can toggle another user superadmin status', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);
    $user = User::factory()->create(['is_superadmin' => false]);

    $response = $this->actingAs($admin)->patch("/superadmin/users/{$user->id}/toggle-superadmin", [
        'is_superadmin' => true,
    ]);

    $response->assertRedirect();
    $this->assertTrue((bool) $user->fresh()->is_superadmin);
});

test('superadmin cannot toggle their own superadmin status', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);

    $response = $this->actingAs($admin)->patch("/superadmin/users/{$admin->id}/toggle-superadmin", [
        'is_superadmin' => false,
    ]);

    $response->assertRedirect();
    $this->assertTrue((bool) $admin->fresh()->is_superadmin);
});

test('superadmin can update module permissions for a user', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->patch("/superadmin/users/{$user->id}/permissions", [
        'permissions' => [
            'ledger' => true,
            'budgets' => false,
            'licenses' => false,
            'loans' => true,
            'recurring' => true,
        ],
    ]);

    $response->assertRedirect();
    $this->assertFalse($user->fresh()->hasPermissionToModule('licenses'));
    $this->assertFalse($user->fresh()->hasPermissionToModule('budgets'));
    $this->assertTrue($user->fresh()->hasPermissionToModule('ledger'));
});

test('module permissions middleware blocks user from access', function () {
    $user = User::factory()->create([
        'is_superadmin' => false,
        'module_permissions' => [
            'ledger' => true,
            'budgets' => true,
            'licenses' => false, // disabled
            'loans' => true,
            'recurring' => true,
        ],
    ]);

    // SaaS Licenses page (/licenses) should redirect to dashboard when disabled
    $response = $this->actingAs($user)->get('/licenses');

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('error', 'The "Licenses" module is currently disabled for your account by the Superadmin.');
});

test('superadmin can ban and unban another user', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);
    $user = User::factory()->create(['is_banned' => false]);

    // Ban
    $response = $this->actingAs($admin)->patch("/superadmin/users/{$user->id}/toggle-ban", [
        'is_banned' => true,
    ]);
    $response->assertRedirect();
    $this->assertTrue((bool) $user->fresh()->is_banned);

    // Unban
    $response = $this->actingAs($admin)->patch("/superadmin/users/{$user->id}/toggle-ban", [
        'is_banned' => false,
    ]);
    $response->assertRedirect();
    $this->assertFalse((bool) $user->fresh()->is_banned);
});

test('banned user is automatically logged out and blocked', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('superadmin can delete a user', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete("/superadmin/users/{$user->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('superadmin cannot ban or delete themselves', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);

    // Self ban attempt
    $response = $this->actingAs($admin)->patch("/superadmin/users/{$admin->id}/toggle-ban", [
        'is_banned' => true,
    ]);
    $response->assertRedirect();
    $response->assertSessionHas('error', 'You cannot ban yourself.');
    $this->assertFalse((bool) $admin->fresh()->is_banned);

    // Self delete attempt
    $response = $this->actingAs($admin)->delete("/superadmin/users/{$admin->id}");
    $response->assertRedirect();
    $response->assertSessionHas('error', 'You cannot delete yourself.');
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
