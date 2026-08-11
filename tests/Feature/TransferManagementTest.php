<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Account;
use Carbon\Carbon;

test('unauthenticated users are redirected from transfer endpoints', function () {
    $this->post('/transfers', [])->assertRedirect('/login');
    $this->patch('/transfers/1', [])->assertRedirect('/login');
});

test('user can create a transfer between two accounts', function () {
    $user = User::factory()->create();

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings Account',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $wallet = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash',
        'initial_balance' => 100.00,
    ]);

    $response = $this->actingAs($user)->post('/transfers', [
        'from_account_id' => $savings->id,
        'to_account_id' => $wallet->id,
        'amount' => 300.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Weekly pocket money',
    ]);

    $response->assertRedirect();

    // Check database has the two linked transactions
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'amount' => 300.00,
        'type' => 'expense',
        'is_transfer' => true,
        'description' => 'Weekly pocket money',
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'amount' => 300.00,
        'type' => 'income',
        'is_transfer' => true,
        'description' => 'Weekly pocket money',
    ]);

    // Check that they are linked to each other
    $expenseTx = Transaction::where('account_id', $savings->id)->where('type', 'expense')->first();
    $incomeTx = Transaction::where('account_id', $wallet->id)->where('type', 'income')->first();

    expect($expenseTx)->not->toBeNull();
    expect($incomeTx)->not->toBeNull();
    expect($expenseTx->transfer_transaction_id)->toBe($incomeTx->id);
    expect($incomeTx->transfer_transaction_id)->toBe($expenseTx->id);

    // Verify balances are computed correctly on the dashboard
    $dashboardResponse = $this->actingAs($user)->get('/dashboard');
    $dashboardResponse->assertStatus(200);

    $inertiaData = $dashboardResponse->original->getData()['page']['props'];
    $savingsData = collect($inertiaData['accounts'])->firstWhere('id', $savings->id);
    $walletData = collect($inertiaData['accounts'])->firstWhere('id', $wallet->id);

    expect($savingsData['current_balance'])->toBe(700.0);
    expect($walletData['current_balance'])->toBe(400.0);
});

test('user cannot transfer to the same account', function () {
    $user = User::factory()->create();

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings Account',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $response = $this->actingAs($user)->post('/transfers', [
        'from_account_id' => $savings->id,
        'to_account_id' => $savings->id,
        'amount' => 300.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Self transfer error',
    ]);

    $response->assertSessionHasErrors(['to_account_id']);
});

test('user can update a transfer', function () {
    $user = User::factory()->create();

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings Account',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $wallet = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash',
        'initial_balance' => 100.00,
    ]);

    // Create a transfer
    $expenseTx = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Transfer',
        'is_transfer' => true,
    ]);

    $incomeTx = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'amount' => 200.00,
        'type' => 'income',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Transfer',
        'is_transfer' => true,
        'transfer_transaction_id' => $expenseTx->id,
    ]);

    $expenseTx->update(['transfer_transaction_id' => $incomeTx->id]);

    // Update the transfer amount to 500.00
    $response = $this->actingAs($user)->patch("/transfers/{$expenseTx->id}", [
        'from_account_id' => $savings->id,
        'to_account_id' => $wallet->id,
        'amount' => 500.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Updated Transfer',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'id' => $expenseTx->id,
        'amount' => 500.00,
        'description' => 'Updated Transfer',
    ]);

    $this->assertDatabaseHas('transactions', [
        'id' => $incomeTx->id,
        'amount' => 500.00,
        'description' => 'Updated Transfer',
    ]);

    // Check balances on dashboard
    $dashboardResponse = $this->actingAs($user)->get('/dashboard');
    $savingsData = collect($dashboardResponse->original->getData()['page']['props']['accounts'])->firstWhere('id', $savings->id);
    $walletData = collect($dashboardResponse->original->getData()['page']['props']['accounts'])->firstWhere('id', $wallet->id);

    expect($savingsData['current_balance'])->toBe(500.0);
    expect($walletData['current_balance'])->toBe(600.0);
});

test('user cannot update other users transfer', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $acc1 = Account::create([
        'user_id' => $user1->id,
        'name' => 'Account 1',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $expenseTx = Transaction::create([
        'user_id' => $user1->id,
        'account_id' => $acc1->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'is_transfer' => true,
    ]);

    // Create valid accounts for user2 so validation passes
    $user2Acc1 = Account::create([
        'user_id' => $user2->id,
        'name' => 'User 2 Account 1',
        'type' => 'bank',
        'initial_balance' => 500.00,
    ]);

    $user2Acc2 = Account::create([
        'user_id' => $user2->id,
        'name' => 'User 2 Account 2',
        'type' => 'cash',
        'initial_balance' => 200.00,
    ]);

    $response = $this->actingAs($user2)->patch("/transfers/{$expenseTx->id}", [
        'from_account_id' => $user2Acc1->id,
        'to_account_id' => $user2Acc2->id,
        'amount' => 500.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
    ]);

    $response->assertStatus(403);
});

test('user deleting one side of transfer deletes both transactions', function () {
    $user = User::factory()->create();

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings Account',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $wallet = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash',
        'initial_balance' => 100.00,
    ]);

    // Create a transfer
    $expenseTx = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Transfer to delete',
        'is_transfer' => true,
    ]);

    $incomeTx = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'amount' => 200.00,
        'type' => 'income',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Transfer to delete',
        'is_transfer' => true,
        'transfer_transaction_id' => $expenseTx->id,
    ]);

    $expenseTx->update(['transfer_transaction_id' => $incomeTx->id]);

    // Delete the expense side
    $response = $this->actingAs($user)->delete("/transactions/{$expenseTx->id}");

    $response->assertRedirect();

    // Both should be missing
    $this->assertDatabaseMissing('transactions', ['id' => $expenseTx->id]);
    $this->assertDatabaseMissing('transactions', ['id' => $incomeTx->id]);

    // Check balances on dashboard return to initial state
    $dashboardResponse = $this->actingAs($user)->get('/dashboard');
    $savingsData = collect($dashboardResponse->original->getData()['page']['props']['accounts'])->firstWhere('id', $savings->id);
    $walletData = collect($dashboardResponse->original->getData()['page']['props']['accounts'])->firstWhere('id', $wallet->id);

    expect($savingsData['current_balance'])->toBe(1000.0);
    expect($walletData['current_balance'])->toBe(100.0);
});
