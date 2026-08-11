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
