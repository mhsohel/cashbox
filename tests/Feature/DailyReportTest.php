<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;

test('unauthenticated users are redirected from daily reports', function () {
    $this->get('/reports/daily')->assertRedirect('/login');
});

test('authenticated users can load daily reports capped at daily share when carryover is positive', function () {
    $user = User::factory()->create();

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

    // Set fixed date: 2026-08-11 (Tuesday)
    $today = Carbon::parse('2026-08-11');

    // Expense budget: 3100.00 (3100 / 31 days = 100 daily share / average budget)
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 3100.00,
        'month' => null,
    ]);

    // Create past transactions: August 2nd and August 5th (total 350.00 spent)
    // Past budget allocation for 10 past days (August 1 to August 10) = 100 * 10 = 1000.00
    // Carryover surplus = 1000.00 - 350.00 = 650.00 (positive)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 150.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-02',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-05',
    ]);

    // Create transaction for today (August 11)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 50.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-11',
    ]);

    $response = $this->actingAs($user)->get('/reports/daily?date=2026-08-11');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    // Daily share (average budget) = 100.00
    // Dynamic pacing budget = (3100 monthly budget - 350 past spent) / 21 remaining days = 130.95
    expect((float) $inertiaData['budget']['expense'])->toBe(130.95);
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    expect((float) $inertiaData['surplus'])->toBe(80.95); // 130.95 budget - 50.00 actual = 80.95 surplus
    expect((float) $inertiaData['cumulative_savings'])->toBe(700.0); // 1100.00 cumulative budget - 400.00 cumulative expenses = 700.00
});

test('daily reports budget drops below daily share when carryover is negative (deficit)', function () {
    $user = User::factory()->create();

    $rent = Category::create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    // Set fixed date: 2026-08-11 (Tuesday)
    $today = Carbon::parse('2026-08-11');

    // Expense budget: 3100.00 (3100 / 31 days = 100 daily share)
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 3100.00,
        'month' => null,
    ]);

    // Create past transaction: August 2nd (total 1200.00 spent)
    // Past budget allocation for 10 past days = 100 * 10 = 1000.00
    // Carryover surplus = 1000.00 - 1200.00 = -200.00 (deficit)
    // Today's cumulative budget = 100.00 (daily share) + (-200.00) = -100.00 (below average budget, not capped)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 1200.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-02',
    ]);

    // Create transaction for today (August 11)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 50.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-11',
    ]);

    $response = $this->actingAs($user)->get('/reports/daily?date=2026-08-11');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    expect((float) $inertiaData['budget']['expense'])->toBe(90.48);
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    expect((float) $inertiaData['surplus'])->toBe(40.48); // 90.48 budget - 50.00 actual = 40.48 surplus
    expect((float) $inertiaData['cumulative_savings'])->toBe(-150.00); // 1100.00 cumulative budget - 1250.00 cumulative expenses = -150.00
});

test('daily reports exclude one_time expenses from calculations', function () {
    $user = User::factory()->create();

    // 1. Create a daily expense category (e.g. Food)
    $food = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'expense_occurrence' => 'daily',
    ]);

    // 2. Create a one-time expense category (e.g. Rent)
    $rent = Category::create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'type' => 'expense',
        'expense_occurrence' => 'one_time',
    ]);

    // Set fixed date: 2026-08-11
    $today = Carbon::parse('2026-08-11');

    // Create budgets:
    // Food (daily): 3100.00 -> 100 daily share
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 3100.00,
        'month' => null,
    ]);

    // Rent (one_time): 5000.00 -> should NOT be included in daily share!
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 5000.00,
        'month' => null,
    ]);

    // Transactions:
    // Past daily expense (Food): 150.00 on August 2nd
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 150.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-02',
    ]);

    // Past one_time expense (Rent): 5000.00 on August 1st -> should NOT count as past expense in carryover
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 5000.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-01',
    ]);

    // Today's daily expense (Food): 50.00 on August 11
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 50.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-11',
    ]);

    // Today's one-time expense (Rent): 200.00 extra on August 11 -> should NOT count as today's daily actual
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $rent->id,
        'amount' => 200.00,
        'type' => 'expense',
        'transaction_date' => '2026-08-11',
    ]);

    $response = $this->actingAs($user)->get('/reports/daily?date=2026-08-11');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    // Food budget only; Rent budget is excluded.
    // Dynamic pacing budget = (3100 monthly budget - 150 past spent) / 21 remaining days = 140.48
    expect((float) $inertiaData['budget']['expense'])->toBe(140.48);
    
    // Today's actual expense = 50.00 (Food only; Rent transaction is excluded)
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    
    // Surplus = 140.48 budget - 50.00 actual = 90.48 surplus
    expect((float) $inertiaData['surplus'])->toBe(90.48);

    // Cumulative savings = 1100.00 - 200.00 = 900.00
    expect((float) $inertiaData['cumulative_savings'])->toBe(900.00);

    // Verify Monthly Daily Progress props:
    expect((float) $inertiaData['monthly_daily_summary']['budget'])->toBe(3100.00); // Food budget only
    expect((float) $inertiaData['monthly_daily_summary']['actual'])->toBe(200.00); // Cumulative daily spent
    expect((float) $inertiaData['monthly_daily_summary']['remaining'])->toBe(2900.00); // 3100 - 200 = 2900

    // Verify Category Breakdown list:
    $catList = collect($inertiaData['daily_category_summary']);
    expect($catList->count())->toBe(1); // Food only
    
    $foodSummary = $catList->firstWhere('name', 'Food');
    expect((float) $foodSummary['budget'])->toBe(3100.00);
    expect((float) $foodSummary['spent'])->toBe(200.00);
    expect((float) $foodSummary['remaining'])->toBe(2900.00);

    // Assert that the transaction list contains only daily transactions
    $txs = collect($inertiaData['transactions']);
    expect($txs->count())->toBe(1); // Food only
    
    $foodTx = $txs->first();
    expect($foodTx['category_name'])->toBe('Food');
    expect($foodTx['expense_occurrence'])->toBe('daily');
});

test('daily reports exclude transfer transactions from calculations and listing', function () {
    $user = User::factory()->create();

    $food = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'expense_occurrence' => 'daily',
    ]);

    $today = Carbon::parse('2026-08-11');

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 3100.00,
        'month' => null,
    ]);

    // Create a normal daily transaction (50.00)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 50.00,
        'type' => 'expense',
        'is_transfer' => false,
        'transaction_date' => '2026-08-11',
    ]);

    // Create a transfer transaction of type expense (1000.00)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'amount' => 1000.00,
        'type' => 'expense',
        'is_transfer' => true,
        'transaction_date' => '2026-08-11',
    ]);

    $response = $this->actingAs($user)->get('/reports/daily?date=2026-08-11');

    $response->assertStatus(200);

    $inertiaData = $response->original->getData()['page']['props'];

    // Today's budget should be 147.62 (3100.00 / 21 days)
    expect((float) $inertiaData['budget']['expense'])->toBe(147.62);

    // Today's actual expense should be 50.00 (the transfer of 1000.00 should be completely ignored)
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    
    // Surplus = 147.62 budget - 50.00 actual = 97.62 surplus (not negative from the transfer)
    expect((float) $inertiaData['surplus'])->toBe(97.62);

    // Assert that the transaction list contains only the non-transfer transaction
    $txs = collect($inertiaData['transactions']);
    expect($txs->count())->toBe(1);
    expect((float) $txs->first()['amount'])->toBe(50.00);
});
