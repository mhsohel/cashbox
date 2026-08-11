<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $this->assertNotNull($user);

    // Verify SaaS licenses is disabled by default
    $this->assertFalse($user->hasPermissionToModule('licenses'));
    $this->assertTrue($user->hasPermissionToModule('ledger'));

    // Verify default categories are seeded
    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
    ]);
    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Food & Dining',
        'type' => 'expense',
    ]);
    $this->assertEquals(10, $user->categories()->count());

    // Verify Cash account is seeded
    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Cash',
        'type' => 'cash',
        'initial_balance' => 0.00,
    ]);
});
