<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

test('unauthenticated users are redirected from reports', function () {
    $this->get('/reports')->assertRedirect('/login');
});

test('authenticated users can load reports page with calculations', function () {
    $user = User::factory()->create();

    // Create Categories
    $salary = Category::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    $rent = Category::create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    // Create transactions for the current month
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $salary->id,
        'amount' => 3000.00,
        'type' => 'income',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 1000.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get('/reports');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    // Verify averages (last 3 months avg: 3000/3 = 1000 income, 1000/3 = 333.33 expense)
    expect(round($inertiaData['averages']['income'], 2))->toBe(1000.0);
    expect(round($inertiaData['averages']['expense'], 2))->toBe(333.33);
    expect(round($inertiaData['averages']['savings'], 2))->toBe(666.67);
    expect(round($inertiaData['averages']['savings_rate'], 2))->toBe(66.67);

    // Verify 12 months projection (666.67 * 12 = 8000.04)
    expect($inertiaData['projections']['twelve_months'])->toBe(8000.04);

    // Verify historical trends structure (last 6 months)
    expect($inertiaData['trends'])->toHaveCount(6);
    
    // Last element should be current month
    $currentMonthTrend = end($inertiaData['trends']);
    expect($currentMonthTrend['income'])->toBe(3000.0);
    expect($currentMonthTrend['expense'])->toBe(1000.0);
    expect($currentMonthTrend['savings'])->toBe(2000.0);
    expect($currentMonthTrend['savings_rate'])->toBe(66.67);

    // Verify category breakdown (last 6 months aggregated)
    expect($inertiaData['category_expenses'])->toHaveCount(1);
    expect($inertiaData['category_expenses'][0]['name'])->toBe('Rent');
    expect($inertiaData['category_expenses'][0]['total'])->toBe(1000.0);
});

test('authenticated ledger users load dashboard with daily summary stats prop', function () {
    $user = User::factory()->create(['is_superadmin' => false, 'module_permissions' => ['ledger' => true]]);

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];
    expect($inertiaData)->toHaveKey('daily_summary');
    expect($inertiaData['daily_summary'])->toHaveKey('budget');
    expect($inertiaData['daily_summary'])->toHaveKey('actual');
    expect($inertiaData['daily_summary'])->toHaveKey('cumulative_savings');
});

test('authenticated standard users load dashboard with daily summary stats prop', function () {
    $user = User::factory()->create(['is_superadmin' => false, 'module_permissions' => ['ledger' => false]]);

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];
    expect($inertiaData)->toHaveKey('daily_summary');
    expect($inertiaData['daily_summary'])->toHaveKey('budget');
    expect($inertiaData['daily_summary'])->toHaveKey('actual');
    expect($inertiaData['daily_summary'])->toHaveKey('cumulative_savings');
});

test('authenticated users can load forecast reports page with transfer exclusions and occurrence averages', function () {
    $user = User::factory()->create();

    // Create Account
    $account1 = \App\Models\Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet 1',
        'type' => 'cash',
        'color' => '#6366f1',
        'initial_balance' => 5000,
    ]);
    $account2 = \App\Models\Account::create([
        'user_id' => $user->id,
        'name' => 'Wallet 2',
        'type' => 'cash',
        'color' => '#10b981',
        'initial_balance' => 2000,
    ]);

    // Create Daily category
    $dailyCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#EF4444',
        'expense_occurrence' => 'daily',
    ]);

    // Create Weekly category
    $weeklyCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Groceries',
        'type' => 'expense',
        'color' => '#8B5CF6',
        'expense_occurrence' => 'weekly_one_time',
    ]);

    // Create budget
    $user->budgets()->create([
        'category_id' => $dailyCat->id,
        'amount' => 500.00,
    ]);

    // Create transaction in last 3 months
    $txnDate = Carbon::now()->subMonth()->format('Y-m-d');
    
    // Normal expense
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account1->id,
        'category_id' => $dailyCat->id,
        'amount' => 300.00,
        'type' => 'expense',
        'transaction_date' => $txnDate,
        'is_transfer' => false,
    ]);

    // Weekly expense
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account1->id,
        'category_id' => $weeklyCat->id,
        'amount' => 150.00,
        'type' => 'expense',
        'transaction_date' => $txnDate,
        'is_transfer' => false,
    ]);

    // Balance transfer transaction (Should be fully excluded from standard averages)
    $transferSource = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account1->id,
        'amount' => 1000.00,
        'type' => 'expense',
        'transaction_date' => $txnDate,
        'is_transfer' => true,
    ]);

    $transferDest = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account2->id,
        'amount' => 1000.00,
        'type' => 'income',
        'transaction_date' => $txnDate,
        'is_transfer' => true,
        'transfer_transaction_id' => $transferSource->id,
    ]);
    $transferSource->update(['transfer_transaction_id' => $transferDest->id]);

    $response = $this->actingAs($user)->get('/reports/forecast');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    // Verify balance transfers are excluded from standard averages:
    // Only standard expenses = 300 (daily) + 150 (weekly) = 450. Over 3 months = 150 average
    expect((float)$inertiaData['averages']['expense'])->toBe(150.0);

    // Verify occurrence_averages:
    // Daily: 300/3 = 100. Weekly: 150/3 = 50. Monthly/One-time: 0
    expect((float)$inertiaData['occurrence_averages']['daily'])->toBe(100.0);
    expect((float)$inertiaData['occurrence_averages']['weekly_one_time'])->toBe(50.0);
    expect((float)$inertiaData['occurrence_averages']['one_time'])->toBe(0.0);

    // Verify budgets list has occurrence details
    expect($inertiaData['budgets'])->toHaveCount(1);
    expect($inertiaData['budgets'][0]['expense_occurrence'])->toBe('daily');
});
