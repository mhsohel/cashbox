<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;

test('unauthenticated users are redirected from loan endpoints', function () {
    $this->get('/loans')->assertRedirect('/login');
    $this->post('/loans', [])->assertRedirect('/login');
    $this->post('/loans/1/repayment', [])->assertRedirect('/login');
    $this->delete('/loans/1')->assertRedirect('/login');
});

test('user can create a lent loan without affecting account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/loans', [
        'person_name' => 'John Doe',
        'type' => 'lent',
        'amount' => 500.00,
        'due_date' => Carbon::tomorrow()->format('Y-m-d'),
        'description' => 'Personal friendly loan',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'person_name' => 'John Doe',
        'type' => 'lent',
        'amount' => 500.00,
        'status' => 'active',
    ]);

    // Ensure no transactions were logged because account_id was omitted
    $this->assertEquals(0, Transaction::count());
});

test('user can create a borrowed loan affecting account balance', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash',
        'initial_balance' => 100.00,
    ]);

    $response = $this->actingAs($user)->post('/loans', [
        'person_name' => 'Bank of America',
        'type' => 'borrowed',
        'amount' => 1000.00,
        'due_date' => Carbon::now()->addMonth()->format('Y-m-d'),
        'description' => 'Business loan',
        'account_id' => $account->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'person_name' => 'Bank of America',
        'type' => 'borrowed',
        'amount' => 1000.00,
    ]);

    // Ensure double-entry transaction was created to increase account balance
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => 1000.00,
        'type' => 'income',
    ]);
});

test('user can log repayment and auto-resolve loan status', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash',
        'initial_balance' => 0.00,
    ]);

    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'Jane Smith',
        'type' => 'lent',
        'amount' => 300.00,
        'status' => 'active',
    ]);

    // Log first part payment
    $response = $this->actingAs($user)->post("/loans/{$loan->id}/repayment", [
        'amount' => 100.00,
        'account_id' => $account->id,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'First installment',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transactions', [
        'loan_id' => $loan->id,
        'amount' => 100.00,
        'type' => 'income',
    ]);

    // Outstanding balance should now be 200, status still active
    $loan->refresh();
    $this->assertEquals('active', $loan->status);

    // Log final settling payment
    $this->actingAs($user)->post("/loans/{$loan->id}/repayment", [
        'amount' => 200.00,
        'account_id' => $account->id,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Final payment',
    ]);

    // Status should auto-update to repaid
    $loan->refresh();
    $this->assertEquals('repaid', $loan->status);
});

test('loan deletion cascades to clear transactions', function () {
    $user = User::factory()->create();
    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'Bob',
        'type' => 'lent',
        'amount' => 150.00,
        'status' => 'active',
    ]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'loan_id' => $loan->id,
        'amount' => 150.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now(),
    ]);

    $this->actingAs($user)->delete("/loans/{$loan->id}")->assertRedirect();

    $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

test('repayment transaction deletion recalculates status', function () {
    $user = User::factory()->create();
    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'Alice',
        'type' => 'lent',
        'amount' => 100.00,
        'status' => 'repaid',
    ]);

    $repayment = Transaction::create([
        'user_id' => $user->id,
        'loan_id' => $loan->id,
        'amount' => 100.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    // Deleting the repayment transaction should update the status back to active
    $this->actingAs($user)->delete("/transactions/{$repayment->id}")->assertRedirect();

    $loan->refresh();
    $this->assertEquals('active', $loan->status);
});
