<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Budget;
use App\Models\Transaction;
use App\Services\BudgetMonitorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('unauthenticated users are redirected from the budget monitor endpoint', function () {
    $this->get('/budgets/monitor')->assertRedirect('/login');
});

test('authenticated users with module permission can access the budget monitor endpoint', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/budgets/monitor');
    
    $response->assertStatus(200);
});

test('authenticated users with budgets module disabled are redirected to dashboard', function () {
    $user = User::factory()->create([
        'module_permissions' => [
            'budgets' => false,
        ]
    ]);
    
    $response = $this->actingAs($user)->get('/budgets/monitor');
    
    $response->assertRedirect('/dashboard');
});


test('pacing calculations for expense and income categories are correct', function () {
    $user = User::factory()->create();
    
    $expenseCategory = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    // Budget of 3000 for the month
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 3100.00,
        'month' => null, // perpetual for expense
    ]);

    // Suppose today is day 10 of a 31-day month
    Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0));

    // Ideal pace = 3100 / 31 = 100 per day.
    // Let's create transactions totaling 1500 (over budget for day 10, actual pace = 150 per day)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 1500.00,
        'type' => 'expense',
        'transaction_date' => '2026-07-05',
    ]);

    $service = new BudgetMonitorService();
    $results = $service->getBudgetInsights($user);

    $catData = collect($results['categories'])->firstWhere('id', $expenseCategory->id);

    expect($catData)->not->toBeNull();
    expect($catData['budget_limit'])->toBe(3100.0);
    expect($catData['actual_amount'])->toBe(1500.0);
    expect($catData['ideal_pace_per_day'])->toBe(100.0);
    expect($catData['actual_pace_per_day'])->toBe(150.0); // 1500 / 10 days
    expect($catData['pacing_ratio_percentage'])->toBe(150.0); // 150% of ideal
    expect($catData['pace_surplus'])->toBe(-500.0); // (100 * 10) - 1500 = -500 deficit
    expect($catData['is_mismatched'])->toBeTrue(); // Over pacing!

    Carbon::setTestNow(); // Reset test time
});

test('fallback advice is used when gemini key is missing', function () {
    $user = User::factory()->create();
    
    $expenseCategory = Category::create([
        'user_id' => $user->id,
        'name' => 'Utilities',
        'type' => 'expense',
        'color' => '#3B82F6',
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 3100.00,
        'month' => null,
    ]);

    Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0));

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 1500.00,
        'type' => 'expense',
        'transaction_date' => '2026-07-05',
    ]);

    // Force clear Gemini config
    config(['services.gemini.key' => null]);

    $service = new BudgetMonitorService();
    $results = $service->getBudgetInsights($user);

    expect($results['ai_global_advice'])->toContain('spending too fast');
    expect($results['ai_global_advice'])->toContain('Utilities');

    Carbon::setTestNow();
});
