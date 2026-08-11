<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Budget;
use Carbon\Carbon;

test('unauthenticated users are redirected from budget endpoints', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->post('/categories', [])->assertRedirect('/login');
    $this->post('/transactions', [])->assertRedirect('/login');
    $this->post('/budgets', [])->assertRedirect('/login');
});

test('user can create a category', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/categories', [
        'name' => 'Groceries',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Groceries',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);
});

test('user can log a transaction', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    $response = $this->actingAs($user)->post('/transactions', [
        'category_id' => $category->id,
        'amount' => 1250.50,
        'type' => 'income',
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'Monthly payout',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 1250.50,
        'type' => 'income',
        'description' => 'Monthly payout',
    ]);
});

test('user can set budget limits', function () {
    $user = User::factory()->create();
    $expenseCategory = Category::create([
        'user_id' => $user->id,
        'name' => 'Utilities',
        'type' => 'expense',
        'color' => '#3B82F6',
    ]);
    $incomeCategory = Category::create([
        'user_id' => $user->id,
        'name' => 'Freelance',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    // 1. Set expense budget (should ignore month, store as NULL)
    $response = $this->actingAs($user)->post('/budgets', [
        'category_id' => $expenseCategory->id,
        'amount' => 300.00,
        'month' => '2026-07',
    ]);
    $response->assertRedirect();
    $this->assertDatabaseHas('budgets', [
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 300.00,
        'month' => null,
    ]);

    // 2. Set income budget (should keep month)
    $response = $this->actingAs($user)->post('/budgets', [
        'category_id' => $incomeCategory->id,
        'amount' => 1500.00,
        'month' => '2026-07',
    ]);
    $response->assertRedirect();
    $this->assertDatabaseHas('budgets', [
        'user_id' => $user->id,
        'category_id' => $incomeCategory->id,
        'amount' => 1500.00,
        'month' => '2026-07',
    ]);
});

test('dashboard correctly calculates net balance and budget limits', function () {
    $user = User::factory()->create();

    // 1. Create Categories
    $salaryCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    $foodCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    // 2. Set Budgets
    Budget::create([
        'user_id' => $user->id,
        'category_id' => $foodCat->id,
        'amount' => 500.00,
        'month' => null, // expense is perpetual
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 2500.00,
        'month' => Carbon::now()->format('Y-m'), // income is monthly
    ]);

    // 3. Add Transactions
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 2000.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $foodCat->id,
        'amount' => 150.00,
        'type' => 'expense',
        'transaction_date' => Carbon::now(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);

    // Verify stats sent to Inertia dashboard
    $inertiaData = $response->original->getData()['page']['props'];

    expect($inertiaData['stats']['net_balance'])->toBe(1850.0);
    expect($inertiaData['stats']['monthly_income'])->toBe(2000.0);
    expect($inertiaData['stats']['monthly_expenses'])->toBe(150.0);

    // Verify budget statistics for Food (Expense)
    $foodData = collect($inertiaData['categories'])->firstWhere('name', 'Food');
    expect($foodData['spent'])->toBe(150.0);
    expect($foodData['budget_limit'])->toBe(500.0);
    expect($foodData['percentage_used'])->toBe(30.0);

    // Verify target statistics for Salary (Income)
    $salaryData = collect($inertiaData['categories'])->firstWhere('name', 'Salary');
    expect($salaryData['earned'])->toBe(2000.0);
    expect($salaryData['budget_limit'])->toBe(2500.0);
    expect($salaryData['percentage_used'])->toBe(80.0);
    expect($salaryData['deficit'])->toBe(500.0);
});

test('reports index correctly calculates income budgets and deficits', function () {
    $user = User::factory()->create();

    $salaryCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    $freelanceCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Freelance',
        'type' => 'income',
        'color' => '#3B82F6',
    ]);

    $foodCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 3000.00,
        'month' => Carbon::now()->format('Y-m'),
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $freelanceCat->id,
        'amount' => 1000.00,
        'month' => Carbon::now()->format('Y-m'),
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $foodCat->id,
        'amount' => 500.00,
        'month' => null, // perpetual expense budget
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 2200.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    // Freelance earned matches target limit exactly (no deficit)
    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $freelanceCat->id,
        'amount' => 1000.00,
        'type' => 'income',
        'transaction_date' => Carbon::now(),
    ]);

    $response = $this->actingAs($user)->get('/reports');

    $response->assertStatus(200);

    $inertiaProps = $response->original->getData()['page']['props'];
    $incomeReport = collect($inertiaProps['income_budget_report']);

    expect($incomeReport->count())->toBe(2);

    $salaryReport = $incomeReport->firstWhere('name', 'Salary');
    expect($salaryReport['limit'])->toBe(3000.0);
    expect($salaryReport['earned'])->toBe(2200.0);
    expect($salaryReport['deficit'])->toBe(800.0);
    expect($salaryReport['status'])->toBe('Deficit');

    $freelanceReport = $incomeReport->firstWhere('name', 'Freelance');
    expect($freelanceReport['limit'])->toBe(1000.0);
    expect($freelanceReport['earned'])->toBe(1000.0);
    expect($freelanceReport['deficit'])->toBe(0.0);
    expect($freelanceReport['status'])->toBe('Met');

    expect($inertiaProps['total_expense_budget'])->toBe(500.0);
    expect($inertiaProps['total_savings_target'])->toBe(3500.0); // 4000.0 (income target) - 500.0 (expense budget)
});

test('user can access monthly report page with calculations', function () {
    $user = User::factory()->create();

    $salaryCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'type' => 'income',
        'color' => '#10B981',
    ]);

    $foodCat = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#EF4444',
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 3000.00,
        'month' => '2026-07',
    ]);

    Budget::create([
        'user_id' => $user->id,
        'category_id' => $foodCat->id,
        'amount' => 500.00,
        'month' => null,
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $salaryCat->id,
        'amount' => 2000.00,
        'type' => 'income',
        'transaction_date' => Carbon::parse('2026-07-15'),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'category_id' => $foodCat->id,
        'amount' => 120.00,
        'type' => 'expense',
        'transaction_date' => Carbon::parse('2026-07-20'),
    ]);

    $response = $this->actingAs($user)->get('/reports/monthly?month=2026-07');

    $response->assertStatus(200);

    $inertiaProps = $response->original->getData()['page']['props'];

    expect($inertiaProps['month'])->toBe('2026-07');
    expect($inertiaProps['summary']['inflow'])->toBe(2000.0);
    expect($inertiaProps['summary']['outflow'])->toBe(120.0);
    expect($inertiaProps['summary']['savings'])->toBe(1880.0);
    expect($inertiaProps['summary']['savings_rate'])->toBe(94.0);

    $expenseItem = collect($inertiaProps['expense_report'])->firstWhere('name', 'Food');
    expect($expenseItem['spent'])->toBe(120.0);
    expect($expenseItem['limit'])->toBe(500.0);
    expect($expenseItem['status'])->toBe('Compliant');

    $incomeItem = collect($inertiaProps['income_report'])->firstWhere('name', 'Salary');
    expect($incomeItem['earned'])->toBe(2000.0);
    expect($incomeItem['limit'])->toBe(3000.0);
    expect($incomeItem['deficit'])->toBe(1000.0);
    expect($incomeItem['status'])->toBe('Deficit');

    $txItem = collect($inertiaProps['transactions'])->first();
    expect($txItem['amount'])->toBe(120.0);
    expect($txItem['category_name'])->toBe('Food');
});
