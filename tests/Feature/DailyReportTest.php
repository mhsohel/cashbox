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
    // Since carryover is +650.00, cumulative budget (750.00) is greater than average budget (100.00).
    // It should be capped at 100.00!
    expect((float) $inertiaData['budget']['expense'])->toBe(100.00);
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    expect((float) $inertiaData['surplus'])->toBe(50.00); // 100.00 budget - 50.00 actual = 50.00 surplus
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

    expect((float) $inertiaData['budget']['expense'])->toBe(-100.00);
    expect((float) $inertiaData['actual']['expense'])->toBe(50.00);
    expect((float) $inertiaData['surplus'])->toBe(-150.00); // -100.00 budget - 50.00 actual = -150.00 surplus
    expect((float) $inertiaData['cumulative_savings'])->toBe(-150.00); // 1100.00 cumulative budget - 1250.00 cumulative expenses = -150.00
});
