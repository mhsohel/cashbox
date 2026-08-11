<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Account;
use Carbon\Carbon;

test('unauthenticated users are redirected from account endpoints', function () {
    $this->post('/accounts', [])->assertRedirect('/login');
    $this->patch('/accounts/1', [])->assertRedirect('/login');
    $this->delete('/accounts/1')->assertRedirect('/login');
});

test('user can create an account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/accounts', [
        'name' => 'City Bank Savings',
        'type' => 'bank',
        'initial_balance' => 1250.00,
        'color' => '#3B82F6',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'City Bank Savings',
        'type' => 'bank',
        'initial_balance' => 1250.00,
        'color' => '#3B82F6',
    ]);
});

test('user can update their own account', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Old Wallet Name',
        'type' => 'cash',
        'initial_balance' => 100.00,
        'color' => '#6B7280',
    ]);

    $response = $this->actingAs($user)->patch("/accounts/{$account->id}", [
        'name' => 'Pocket Cash',
        'type' => 'cash',
        'initial_balance' => 150.00,
        'color' => '#10B981',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'user_id' => $user->id,
        'name' => 'Pocket Cash',
        'type' => 'cash',
        'initial_balance' => 150.00,
        'color' => '#10B981',
    ]);
});

test('user cannot update other users accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $account = Account::create([
        'user_id' => $user1->id,
        'name' => 'Secret Stash',
        'type' => 'cash',
        'initial_balance' => 5000.00,
    ]);

    $response = $this->actingAs($user2)->patch("/accounts/{$account->id}", [
        'name' => 'Stolen Stash',
        'type' => 'cash',
        'initial_balance' => 0.00,
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'Secret Stash',
        'initial_balance' => 5000.00,
    ]);
});

test('user can delete their own account', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Temporary Account',
        'type' => 'other',
        'initial_balance' => 0.00,
    ]);

    $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('accounts', [
        'id' => $account->id,
    ]);
});

test('dashboard computes account balances correctly', function () {
    $user = User::factory()->create();

    $bKash = Account::create([
        'user_id' => $user->id,
        'name' => 'bKash Personal',
        'type' => 'mobile_wallet',
        'initial_balance' => 500.00,
    ]);

    // Create a category
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Miscellaneous',
        'type' => 'expense',
    ]);

    // Income transaction: +250
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $bKash->id,
        'category_id' => $category->id,
        'amount' => 250.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    // Expense transaction: -100
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $bKash->id,
        'category_id' => $category->id,
        'amount' => 100.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];
    
    // Find bKash personal account and verify its current balance
    $bKashData = collect($inertiaData['accounts'])->firstWhere('name', 'bKash Personal');
    expect($bKashData)->not->toBeNull();
    expect($bKashData['current_balance'])->toBe(650.0);
});

test('reports index page calculates accounts report correctly', function () {
    $user = User::factory()->create();

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Bank Savings',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Investments',
        'type' => 'income',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'category_id' => $category->id,
        'amount' => 500.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'category_id' => $category->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now(),
    ]);

    $response = $this->actingAs($user)->get('/reports');
    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];
    $reportData = collect($inertiaData['accounts_report'])->firstWhere('name', 'Bank Savings');

    expect($reportData)->not->toBeNull();
    expect($reportData['initial_balance'])->toBe(1000.0);
    expect($reportData['total_inflows'])->toBe(500.0);
    expect($reportData['total_outflows'])->toBe(200.0);
    expect($reportData['net_flow'])->toBe(300.0);
    expect($reportData['current_balance'])->toBe(1300.0);
});
