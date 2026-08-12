<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if ($user->hasPermissionToModule('ledger')) {
            return (new \App\Http\Controllers\ReportController())->finance($request);
        }
        
        // Default to current month if not specified
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        // 1. Overall Balance (all-time, excluding transfers)
        $totalIncomeAllTime = $user->transactions()
            ->where('type', 'income')
            ->where('is_transfer', false)
            ->sum('amount');
            
        $totalExpenseAllTime = $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->sum('amount');
            
        $netBalance = $totalIncomeAllTime - $totalExpenseAllTime;

        // 2. Monthly Income (excluding transfers)
        $monthlyIncome = $user->transactions()
            ->where('type', 'income')
            ->where('is_transfer', false)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        // 3. Monthly Expenses (excluding transfers)
        $monthlyExpenses = $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        // 4. Categories with budget limit and actual spending for this month (excluding transfers)
        $categoriesData = $user->categories()
            ->get()
            ->map(function (Category $category) use ($startDate, $endDate, $month) {
                // Sum of expenses for this category in the month
                $spent = $category->transactions()
                    ->where('type', 'expense')
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                // Sum of income for this category in the month
                $earned = $category->transactions()
                    ->where('type', 'income')
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                // Find budget limit and calculate progress based on type
                if ($category->type === 'expense') {
                    $budget = $category->budgets()->whereNull('month')->first();
                    $limit = $budget ? (float) $budget->amount : 0.0;
                    $percentage = $limit > 0 ? round(($spent / $limit) * 100, 2) : 0.0;
                    $deficit = 0.0;
                } else {
                    $budget = $category->budgets()->where('month', $month)->first();
                    $limit = $budget ? (float) $budget->amount : 0.0;
                    $percentage = $limit > 0 ? round(($earned / $limit) * 100, 2) : 0.0;
                    $deficit = $limit > 0 ? max(0.0, $limit - $earned) : 0.0;
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color ?? '#3B82F6',
                    'spent' => (float) $spent,
                    'earned' => (float) $earned,
                    'budget_limit' => $limit,
                    'percentage_used' => $percentage,
                    'deficit' => (float) $deficit,
                    'expense_occurrence' => $category->expense_occurrence ?? 'daily',
                ];
            });

        // 5. User accounts list with current balances
        $accountsData = $user->accounts()
            ->get()
            ->map(function ($account) {
                $totalIncome = $account->transactions()
                    ->where('type', 'income')
                    ->sum('amount');
                    
                $totalExpense = $account->transactions()
                    ->where('type', 'expense')
                    ->sum('amount');
                    
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'initial_balance' => (float) $account->initial_balance,
                    'color' => $account->color ?? '#6366f1',
                    'current_balance' => round((float) $account->initial_balance + (float) $totalIncome - (float) $totalExpense, 2),
                ];
            });

        // 6. Recent Transaction ledger (all transactions in the selected month) with categories and accounts
        $recentTransactions = $user->transactions()
            ->with(['category', 'account', 'transferTransaction.account'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Transaction $tx) {
                return [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'type' => $tx->type,
                    'transaction_date' => $tx->transaction_date->format('Y-m-d'),
                    'description' => $tx->description,
                    'category' => $tx->category ? [
                        'id' => $tx->category->id,
                        'name' => $tx->category->name,
                        'color' => $tx->category->color ?? '#3B82F6',
                    ] : null,
                    'account' => $tx->account ? [
                        'id' => $tx->account->id,
                        'name' => $tx->account->name,
                        'color' => $tx->account->color ?? '#6366f1',
                        'type' => $tx->account->type,
                    ] : null,
                    'is_transfer' => (bool) $tx->is_transfer,
                    'transfer_account' => ($tx->is_transfer && $tx->transferTransaction && $tx->transferTransaction->account) ? [
                        'id' => $tx->transferTransaction->account->id,
                        'name' => $tx->transferTransaction->account->name,
                        'color' => $tx->transferTransaction->account->color ?? '#6366f1',
                        'type' => $tx->transferTransaction->account->type,
                    ] : null,
                ];
            });

        $reminders = $user->recurringSchedules()
            ->with(['account', 'category', 'loan'])
            ->where('is_active', true)
            ->where('next_due_date', '<=', Carbon::today()->addDays(7))
            ->orderBy('next_due_date', 'asc')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'type' => $schedule->type,
                    'frequency' => $schedule->frequency,
                    'next_due_date' => $schedule->next_due_date->format('Y-m-d'),
                    'amount' => (float) $schedule->amount,
                    'description' => $schedule->description,
                    'account' => $schedule->account ? [
                        'id' => $schedule->account->id,
                        'name' => $schedule->account->name,
                        'color' => $schedule->account->color,
                    ] : null,
                    'category' => $schedule->category ? [
                        'id' => $schedule->category->id,
                        'name' => $schedule->category->name,
                        'color' => $schedule->category->color,
                    ] : null,
                    'loan' => $schedule->loan ? [
                        'id' => $schedule->loan->id,
                        'person_name' => $schedule->loan->person_name,
                        'type' => $schedule->loan->type,
                    ] : null,
                    'loan_type' => $schedule->loan_type,
                    'person_name' => $schedule->person_name,
                    'is_overdue' => $schedule->next_due_date->lt(Carbon::today()),
                ];
            });

        // Calculate Today's Daily Summary
        $today = Carbon::now();
        $dateStr = $today->format('Y-m-d');
        $daysInMonth = $today->daysInMonth;
        $dayOfMonth = $today->day;

        // Fetch monthly budget limit totals to apportion
        // 1. Expense budget: perpetual (month is null)
        $expenseBudgetTotal = (float) $user->budgets()
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense')
                  ->where('expense_occurrence', 'daily');
            })
            ->whereNull('month')
            ->sum('amount');

        // Apportion to daily share
        $dailyShare = $daysInMonth > 0 ? round($expenseBudgetTotal / $daysInMonth, 2) : 0.0;

        // Calculate cumulative actual spent from past days of this month
        $actualExpensePast = 0.0;
        if ($dayOfMonth > 1) {
            $startOfMonth = $today->copy()->startOfMonth();
            $yesterdayEnd = $today->copy()->subDay()->endOfDay();

            $actualExpensePast = (float) $user->transactions()
                ->where('type', 'expense')
                ->where('is_transfer', false)
                ->where(function ($query) {
                    $query->whereHas('category', function ($q) {
                        $q->where('expense_occurrence', 'daily');
                    })->orWhereNull('category_id');
                })
                ->whereBetween('transaction_date', [$startOfMonth, $yesterdayEnd])
                ->sum('amount');
        }

        // Today's budget = Monthly Daily-Expense Remaining / remaining day count
        $remainingDays = $daysInMonth - $dayOfMonth + 1;
        $monthlyRemainingBeforeToday = $expenseBudgetTotal - $actualExpensePast;
        $dailyExpenseBudget = $remainingDays > 0 ? round($monthlyRemainingBeforeToday / $remainingDays, 2) : 0.0;
        if ($dailyExpenseBudget < 0) {
            $dailyExpenseBudget = 0.0;
        }

        // Fetch actuals for today
        $actualExpenseToday = (float) $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->where(function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('expense_occurrence', 'daily');
                })->orWhereNull('category_id');
            })
            ->whereDate('transaction_date', $dateStr)
            ->sum('amount');

        // Calculate cumulative savings up to today
        $startOfMonth = $today->copy()->startOfMonth();
        $todayEnd = $today->copy()->endOfDay();
        
        $cumulativeBudget = round($dailyShare * $dayOfMonth, 2);
        
        $actualExpenseCumulative = (float) $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->where(function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('expense_occurrence', 'daily');
                })->orWhereNull('category_id');
            })
            ->whereBetween('transaction_date', [$startOfMonth, $todayEnd])
            ->sum('amount');

        $cumulativeSavings = round($cumulativeBudget - $actualExpenseCumulative, 2);

        $dailySummary = [
            'budget' => $dailyExpenseBudget,
            'actual' => $actualExpenseToday,
            'cumulative_savings' => $cumulativeSavings,
            'average_budget' => $dailyShare,
        ];

        return Inertia::render('Dashboard', [
            'stats' => [
                'net_balance' => (float) $netBalance,
                'monthly_income' => (float) $monthlyIncome,
                'monthly_expenses' => (float) $monthlyExpenses,
            ],
            'categories' => $categoriesData,
            'accounts' => $accountsData,
            'recent_transactions' => $recentTransactions,
            'current_month' => $month,
            'reminders' => $reminders,
            'daily_summary' => $dailySummary,
        ]);
    }
}
