<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\RecurringSchedule;
use App\Models\Transaction;
use Carbon\Carbon;

test('unauthenticated users are redirected from recurring endpoints', function () {
    $this->get('/recurring')->assertRedirect('/login');
    $this->post('/recurring', [])->assertRedirect('/login');
    $this->patch('/recurring/1', [])->assertRedirect('/login');
    $this->delete('/recurring/1')->assertRedirect('/login');
    $this->post('/recurring/1/process')->assertRedirect('/login');
    $this->post('/recurring/1/skip')->assertRedirect('/login');
    $this->post('/recurring/1/toggle')->assertRedirect('/login');
});

test('user can create a recurring expense schedule', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Bank',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'type' => 'expense',
    ]);

    $response = $this->actingAs($user)->post('/recurring', [
        'type' => 'expense',
        'frequency' => 'monthly',
        'amount' => 500.00,
        'start_date' => '2026-07-01',
        'description' => 'Monthly apartment rent',
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('recurring_schedules', [
        'user_id' => $user->id,
        'type' => 'expense',
        'frequency' => 'monthly',
        'amount' => 500.00,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'is_active' => 1,
    ]);
    $schedule = RecurringSchedule::first();
    $this->assertEquals('2026-07-01', $schedule->start_date->format('Y-m-d'));
    $this->assertEquals('2026-07-01', $schedule->next_due_date->format('Y-m-d'));
});

test('user can create a recurring loan installment schedule', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => 'cash',
        'initial_balance' => 500.00,
    ]);
    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'John Doe',
        'type' => 'borrowed',
        'amount' => 1000.00,
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->post('/recurring', [
        'type' => 'loan_installment',
        'frequency' => 'weekly',
        'amount' => 100.00,
        'start_date' => '2026-07-01',
        'description' => 'Weekly loan repayment',
        'account_id' => $account->id,
        'loan_id' => $loan->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('recurring_schedules', [
        'user_id' => $user->id,
        'type' => 'loan_installment',
        'frequency' => 'weekly',
        'amount' => 100.00,
        'loan_id' => $loan->id,
    ]);
});

test('user can create a recurring loan/borrow schedule', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => 'cash',
        'initial_balance' => 0.00,
    ]);

    $response = $this->actingAs($user)->post('/recurring', [
        'type' => 'loan',
        'frequency' => 'quarterly',
        'amount' => 200.00,
        'start_date' => '2026-07-01',
        'loan_type' => 'borrowed',
        'person_name' => 'Uncle Bob',
        'description' => 'Quarterly allowance loan',
        'account_id' => $account->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('recurring_schedules', [
        'user_id' => $user->id,
        'type' => 'loan',
        'frequency' => 'quarterly',
        'amount' => 200.00,
        'loan_type' => 'borrowed',
        'person_name' => 'Uncle Bob',
    ]);
});

test('user can toggle active status of a schedule', function () {
    $user = User::factory()->create();
    $schedule = RecurringSchedule::create([
        'user_id' => $user->id,
        'type' => 'expense',
        'frequency' => 'monthly',
        'amount' => 50.00,
        'start_date' => Carbon::today(),
        'next_due_date' => Carbon::today(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post("/recurring/{$schedule->id}/toggle");
    $response->assertRedirect();
    $this->assertDatabaseHas('recurring_schedules', [
        'id' => $schedule->id,
        'is_active' => 0,
    ]);

    $this->actingAs($user)->post("/recurring/{$schedule->id}/toggle");
    $this->assertDatabaseHas('recurring_schedules', [
        'id' => $schedule->id,
        'is_active' => 1,
    ]);
});

test('processing an expense schedule creates transaction and advances next due date', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => 'cash',
        'initial_balance' => 500.00,
    ]);
    $schedule = RecurringSchedule::create([
        'user_id' => $user->id,
        'type' => 'expense',
        'frequency' => 'monthly',
        'amount' => 100.00,
        'start_date' => Carbon::parse('2026-07-01'),
        'next_due_date' => Carbon::parse('2026-07-01'),
        'account_id' => $account->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post("/recurring/{$schedule->id}/process");
    $response->assertRedirect();

    // Verify transaction created
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => 100.00,
        'type' => 'expense',
        'recurring_schedule_id' => $schedule->id,
    ]);
    $tx = Transaction::where('recurring_schedule_id', $schedule->id)->first();
    $this->assertEquals('2026-07-01', $tx->transaction_date->format('Y-m-d'));

    // Verify schedule updated
    $schedule->refresh();
    $this->assertEquals('2026-07-01', $schedule->last_run_date->format('Y-m-d'));
    $this->assertEquals('2026-08-01', $schedule->next_due_date->format('Y-m-d')); // +1 month
});

test('processing a loan installment schedule creates repayment transaction and advances next due date', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => 'cash',
        'initial_balance' => 500.00,
    ]);
    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'Jane',
        'type' => 'borrowed', // we borrowed, so repaying is an expense
        'amount' => 200.00,
        'status' => 'active',
    ]);
    $schedule = RecurringSchedule::create([
        'user_id' => $user->id,
        'type' => 'loan_installment',
        'frequency' => 'weekly',
        'amount' => 100.00,
        'start_date' => Carbon::parse('2026-07-01'),
        'next_due_date' => Carbon::parse('2026-07-01'),
        'account_id' => $account->id,
        'loan_id' => $loan->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post("/recurring/{$schedule->id}/process");

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'loan_id' => $loan->id,
        'amount' => 100.00,
        'type' => 'expense',
    ]);
    $tx = Transaction::where('loan_id', $loan->id)->first();
    $this->assertEquals('2026-07-01', $tx->transaction_date->format('Y-m-d'));

    $schedule->refresh();
    $this->assertEquals('2026-07-08', $schedule->next_due_date->format('Y-m-d')); // +1 week
    
    // Loan should still be active since repayments (100) < loan amount (200)
    $loan->refresh();
    $this->assertEquals('active', $loan->status);

    // Process second installment
    $this->actingAs($user)->post("/recurring/{$schedule->id}/process");

    // Loan should now be repaid since total repayments (200) >= loan amount (200)
    $loan->refresh();
    $this->assertEquals('repaid', $loan->status);
});

test('skipping a schedule advances next due date without logging a transaction', function () {
    $user = User::factory()->create();
    $schedule = RecurringSchedule::create([
        'user_id' => $user->id,
        'type' => 'expense',
        'frequency' => 'quarterly',
        'amount' => 300.00,
        'start_date' => Carbon::parse('2026-07-01'),
        'next_due_date' => Carbon::parse('2026-07-01'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post("/recurring/{$schedule->id}/skip");
    $response->assertRedirect();

    $this->assertEquals(0, Transaction::count());

    $schedule->refresh();
    $this->assertEquals('2026-07-01', $schedule->last_run_date->format('Y-m-d'));
    $this->assertEquals('2026-10-01', $schedule->next_due_date->format('Y-m-d')); // +3 months
});
