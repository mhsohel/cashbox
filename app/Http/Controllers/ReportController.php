<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. Calculate Historical Trends (Last 6 Months, excluding Transfers)
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->format('M Y');
            
            $startDate = $monthDate->copy()->startOfMonth();
            $endDate = $monthDate->copy()->endOfMonth();
            
            $income = $user->transactions()
                ->where('type', 'income')
                ->where('is_transfer', false)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
                
            $expense = $user->transactions()
                ->where('type', 'expense')
                ->where('is_transfer', false)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $savings = $income - $expense;
            $savingsRate = $income > 0 ? round(($savings / $income) * 100, 2) : 0.0;
            
            $trends[] = [
                'month_key' => $monthKey,
                'label' => $monthLabel,
                'income' => (float) $income,
                'expense' => (float) $expense,
                'savings' => (float) $savings,
                'savings_rate' => (float) $savingsRate,
            ];
        }

        // 2. Calculate Category Breakdown (Last 6 Months aggregated, excluding Transfers)
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $todayEnd = Carbon::now()->endOfMonth();

        $categoryExpenses = $user->categories()
            ->where('type', 'expense')
            ->get()
            ->map(function (Category $category) use ($sixMonthsAgo, $todayEnd) {
                $totalSpent = $category->transactions()
                    ->where('type', 'expense')
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$sixMonthsAgo, $todayEnd])
                    ->sum('amount');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color ?? '#3B82F6',
                    'total' => (float) $totalSpent,
                ];
            })
            ->filter(fn($cat) => $cat['total'] > 0)
            ->values();

        // 3. Projections based on Last 3 Months average (excluding Transfers)
        $threeMonthsAgo = Carbon::now()->subMonths(2)->startOfMonth();
        
        $totalIncomeLast3 = $user->transactions()
            ->where('type', 'income')
            ->where('is_transfer', false)
            ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
            ->sum('amount');

        $totalExpenseLast3 = $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
            ->sum('amount');

        $avgIncome = round($totalIncomeLast3 / 3, 2);
        $avgExpense = round($totalExpenseLast3 / 3, 2);
        $avgSavings = $avgIncome - $avgExpense;
        $avgSavingsRate = $avgIncome > 0 ? round(($avgSavings / $avgIncome) * 100, 2) : 0.0;

        // Projections
        $projection3m = round($avgSavings * 3, 2);
        $projection6m = round($avgSavings * 6, 2);
        $projection12m = round($avgSavings * 12, 2);

        // 4. Accounts Report
        $accountsReport = $user->accounts()->get()->map(function ($account) {
            $inflows = $account->transactions()->where('type', 'income')->sum('amount');
            $outflows = $account->transactions()->where('type', 'expense')->sum('amount');
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'color' => $account->color ?? '#6366f1',
                'initial_balance' => (float) $account->initial_balance,
                'total_inflows' => (float) $inflows,
                'total_outflows' => (float) $outflows,
                'net_flow' => (float) ($inflows - $outflows),
                'current_balance' => (float) ($account->initial_balance + $inflows - $outflows),
            ];
        });

        // 5. Income Budget & Deficit Report for the current month
        $currentMonth = Carbon::now()->format('Y-m');
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $incomeBudgetReport = $user->categories()
            ->where('type', 'income')
            ->get()
            ->map(function (Category $category) use ($currentMonth, $startOfMonth, $endOfMonth) {
                $budget = $category->budgets()->where('month', $currentMonth)->first();
                $limit = $budget ? (float) $budget->amount : 0.0;

                $earned = $category->transactions()
                    ->where('type', 'income')
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
                    ->sum('amount');

                $deficit = $limit > 0 ? max(0.0, $limit - $earned) : 0.0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color ?? '#10B981',
                    'limit' => $limit,
                    'earned' => (float) $earned,
                    'deficit' => $deficit,
                    'status' => $limit > 0 ? ($earned >= $limit ? 'Met' : 'Deficit') : 'No Target',
                ];
            })
            ->filter(fn($item) => $item['limit'] > 0)
            ->values();

        // Total Expense Budget (perpetual, active limit of all expense categories)
        $totalExpenseBudget = (float) $user->budgets()->whereHas('category', function ($query) {
            $query->where('type', 'expense');
        })->sum('amount');

        // Total Income Target (sum of current month's income category targets)
        $totalIncomeTarget = (float) $incomeBudgetReport->sum('limit');

        // Total Savings Target = Total Income Target - Total Expense Budget
        $totalSavingsTarget = (float) ($totalIncomeTarget - $totalExpenseBudget);

        return Inertia::render('Reports/Index', [
            'trends' => $trends,
            'category_expenses' => $categoryExpenses,
            'accounts_report' => $accountsReport,
            'income_budget_report' => $incomeBudgetReport,
            'total_expense_budget' => $totalExpenseBudget,
            'total_savings_target' => $totalSavingsTarget,
            'averages' => [
                'income' => (float) $avgIncome,
                'expense' => (float) $avgExpense,
                'savings' => (float) $avgSavings,
                'savings_rate' => (float) $avgSavingsRate,
            ],
            'projections' => [
                'three_months' => (float) $projection3m,
                'six_months' => (float) $projection6m,
                'twelve_months' => (float) $projection12m,
            ]
        ]);
    }

    public function forecast(Request $request): Response
    {
        $user = Auth::user();

        // 1. Starting liquid balance / Net Worth calculation
        $accounts = $user->accounts()->get()->map(function ($account) {
            $inflows = $account->transactions()->where('type', 'income')->sum('amount');
            $outflows = $account->transactions()->where('type', 'expense')->sum('amount');
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'color' => $account->color ?? '#6366f1',
                'current_balance' => (float) ($account->initial_balance + $inflows - $outflows),
            ];
        });

        $startingNetWorth = $accounts->sum('current_balance');

        // 2. Calculate average monthly income/expense (last 3 months, excluding SaaS, Loans, and Transfers)
        $todayEnd = Carbon::now()->endOfMonth();
        $threeMonthsAgo = Carbon::now()->subMonths(2)->startOfMonth();

        $standardIncome = $user->transactions()
            ->where('type', 'income')
            ->where('is_transfer', false)
            ->whereNull('loan_id')
            ->whereNull('license_id')
            ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
            ->sum('amount');

        $standardExpense = $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->whereNull('loan_id')
            ->whereNull('license_id')
            ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
            ->sum('amount');

        $avgIncome = round($standardIncome / 3, 2);
        $avgExpense = round($standardExpense / 3, 2);

        // 2b. Calculate average spending breakdown by occurrence type
        $occurrenceSpend = $user->transactions()
            ->selectRaw('coalesce(categories.expense_occurrence, \'daily\') as occurrence, sum(transactions.amount) as total')
            ->where('transactions.type', 'expense')
            ->where('is_transfer', false)
            ->whereNull('loan_id')
            ->whereNull('license_id')
            ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->groupBy('occurrence')
            ->get()
            ->pluck('total', 'occurrence')
            ->toArray();

        $avgDailySpend = round(($occurrenceSpend['daily'] ?? 0) / 3, 2);
        $avgWeeklySpend = round(($occurrenceSpend['weekly_one_time'] ?? 0) / 3, 2);
        $avgMonthlySpend = round(($occurrenceSpend['one_time'] ?? 0) / 3, 2);

        // 3. Load active SaaS licenses
        $licenses = $user->licenses()
            ->where('status', 'active')
            ->get()
            ->map(function ($license) {
                return [
                    'id' => $license->id,
                    'client_name' => $license->client->name ?? 'Direct License',
                    'amount' => (float) $license->amount,
                    'billing_cycle' => $license->billing_cycle,
                    'next_renewal_date' => $license->next_renewal_date ? $license->next_renewal_date->format('Y-m-d') : null,
                ];
            });

        // 4. Load active Loans
        $loans = $user->loans()
            ->where('status', 'active')
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'person_name' => $loan->person_name,
                    'type' => $loan->type,
                    'amount' => (float) $loan->amount,
                    'due_date' => $loan->due_date ? $loan->due_date->format('Y-m-d') : null,
                    'description' => $loan->description,
                ];
            });

        // 5. Load Budgets & Average Spend
        $budgets = $user->budgets()
            ->with('category')
            ->get()
            ->map(function ($budget) use ($threeMonthsAgo, $todayEnd) {
                $category = $budget->category;
                if (!$category) return null;

                $threeMonthTotal = $category->transactions()
                    ->where('type', $category->type)
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$threeMonthsAgo, $todayEnd])
                    ->sum('amount');

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_type' => $category->type,
                    'color' => $category->color ?? '#3B82F6',
                    'limit' => (float) $budget->amount,
                    'avg_spend' => round($threeMonthTotal / 3, 2),
                    'expense_occurrence' => $category->expense_occurrence ?? 'daily',
                ];
            })
            ->filter()
            ->values();

        // 6. Generate 12-Month list (labels and dates) for predictions
        $timelineMonths = [];
        $currentMonth = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $monthDate = $currentMonth->copy()->addMonths($i);
            $timelineMonths[] = [
                'month_key' => $monthDate->format('Y-m'),
                'label' => $monthDate->format('M Y'),
                'year' => (int) $monthDate->format('Y'),
                'month' => (int) $monthDate->format('m'),
            ];
        }

        return Inertia::render('Reports/Forecast', [
            'accounts' => $accounts,
            'starting_net_worth' => (float) $startingNetWorth,
            'averages' => [
                'income' => (float) $avgIncome,
                'expense' => (float) $avgExpense,
            ],
            'occurrence_averages' => [
                'daily' => (float) $avgDailySpend,
                'weekly_one_time' => (float) $avgWeeklySpend,
                'one_time' => (float) $avgMonthlySpend,
            ],
            'licenses' => $licenses,
            'loans' => $loans,
            'budgets' => $budgets,
            'timeline_months' => $timelineMonths,
        ]);
    }

    public function monthly(Request $request): Response
    {
        $user = Auth::user();
        $month = $request->input('month', Carbon::now()->format('Y-m'));

        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();

        // 1. Inflow, Outflow, Net, Savings Rate for this month
        $inflow = (float) $user->transactions()
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $outflow = (float) $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $savings = $inflow - $outflow;
        $savingsRate = $inflow > 0 ? round(($savings / $inflow) * 100, 2) : 0.0;

        // 2. Expense Budgets Breakdown for this month (using perpetual budget or actual spent)
        $expenseReport = $user->categories()
            ->where('type', 'expense')
            ->get()
            ->map(function (Category $category) use ($startDate, $endDate) {
                $spent = (float) $category->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                $budget = $category->budgets()->whereNull('month')->first();
                $limit = $budget ? (float) $budget->amount : 0.0;
                $percentage = $limit > 0 ? round(($spent / $limit) * 100, 2) : 0.0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color ?? '#3B82F6',
                    'spent' => $spent,
                    'limit' => $limit,
                    'percentage' => $percentage,
                    'status' => $limit > 0 ? ($spent > $limit ? 'Over Limit' : 'Compliant') : 'No Budget',
                ];
            });

        // 3. Income Targets Breakdown for this month
        $incomeReport = $user->categories()
            ->where('type', 'income')
            ->get()
            ->map(function (Category $category) use ($month, $startDate, $endDate) {
                $earned = (float) $category->transactions()
                    ->where('type', 'income')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                $budget = $category->budgets()->where('month', $month)->first();
                $limit = $budget ? (float) $budget->amount : 0.0;
                $percentage = $limit > 0 ? round(($earned / $limit) * 100, 2) : 0.0;
                $deficit = $limit > 0 ? max(0.0, $limit - $earned) : 0.0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color ?? '#10B981',
                    'earned' => $earned,
                    'limit' => $limit,
                    'percentage' => $percentage,
                    'deficit' => $deficit,
                    'status' => $limit > 0 ? ($earned >= $limit ? 'Met' : 'Deficit') : 'No Target',
                ];
            });

        // 4. Accounts Performance statement for this month
        $accountsReport = $user->accounts()
            ->get()
            ->map(function ($account) use ($startDate, $endDate) {
                $inflows = $account->transactions()
                    ->where('type', 'income')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                $outflows = $account->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'color' => $account->color ?? '#3B82F6',
                    'type' => $account->type,
                    'inflows' => (float) $inflows,
                    'outflows' => (float) $outflows,
                    'net_flow' => (float) ($inflows - $outflows),
                ];
            });

        // 5. Transaction Ledger (list of all transactions in this month)
        $transactions = $user->transactions()
            ->with(['category', 'account'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'amount' => (float) $t->amount,
                    'type' => $t->type,
                    'description' => $t->description,
                    'transaction_date' => $t->transaction_date->format('Y-m-d'),
                    'category_name' => $t->category?->name ?? 'Uncategorized',
                    'category_color' => $t->category?->color ?? '#64748B',
                    'account_name' => $t->account?->name ?? 'N/A',
                ];
            });

        return Inertia::render('Reports/Monthly', [
            'month' => $month,
            'summary' => [
                'inflow' => $inflow,
                'outflow' => $outflow,
                'savings' => $savings,
                'savings_rate' => $savingsRate,
            ],
            'expense_report' => $expenseReport,
            'income_report' => $incomeReport,
            'accounts_report' => $accountsReport,
            'transactions' => $transactions,
        ]);
    }

    public function finance(Request $request): Response
    {
        $user = Auth::user();
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // 1. Current Balance across all accounts
        $accounts = $user->accounts()->get()->map(function ($account) {
            $inflows = (float) $account->transactions()->where('type', 'income')->sum('amount');
            $outflows = (float) $account->transactions()->where('type', 'expense')->sum('amount');
            $currentBalance = (float) ($account->initial_balance + $inflows - $outflows);
            
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type ?? 'Savings',
                'color' => $account->color ?? '#6366f1',
                'balance' => $currentBalance,
                'current_balance' => $currentBalance,
                'initial_balance' => (float) $account->initial_balance,
                'last4' => str_pad($account->id, 4, '0', STR_PAD_LEFT),
            ];
        });

        $totalBalance = (float) (
            $user->accounts()->sum('initial_balance') + 
            $user->transactions()->where('type', 'income')->sum('amount') - 
            $user->transactions()->where('type', 'expense')->sum('amount')
        );

        // 2. Inflow / Outflow / Savings for current month
        $inflow = (float) $user->transactions()
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $outflow = (float) $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $savings = $inflow - $outflow;
        $savingsRate = $inflow > 0 ? round(($savings / $inflow) * 100, 1) : 0.0;

        // 3. Last month metrics for MoM percentage comparisons
        $lastInflow = (float) $user->transactions()
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $lastOutflow = (float) $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        // MoM changes
        $incomeChangePct = $lastInflow > 0 ? round((($inflow - $lastInflow) / $lastInflow) * 100, 1) : 0.0;
        $spentChangePct = $lastOutflow > 0 ? round((($outflow - $lastOutflow) / $lastOutflow) * 100, 1) : 0.0;

        // 4. Sparkline balance history (last 15 days cumulative)
        $sparklineData = [];
        $runningBalance = $totalBalance;
        
        for ($i = 0; $i < 15; $i++) {
            $day = Carbon::now()->subDays($i);
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dayInflow = (float) $user->transactions()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$dayStart, $dayEnd])
                ->sum('amount');
            $dayOutflow = (float) $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$dayStart, $dayEnd])
                ->sum('amount');
            
            $dayNet = $dayInflow - $dayOutflow;
            
            $sparklineData[] = [
                'date' => $day->format('M d'),
                'balance' => $runningBalance
            ];

            $runningBalance -= $dayNet;
        }
        $sparklineData = array_reverse($sparklineData);

        // 5. Cashflow trends (last 12 months)
        $cashflowTrends = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthLabel = $monthDate->format('M Y');
            
            $mStart = $monthDate->copy()->startOfMonth();
            $mEnd = $monthDate->copy()->endOfMonth();
            
            $mIncome = (float) $user->transactions()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$mStart, $mEnd])
                ->sum('amount');
                
            $mExpense = (float) $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$mStart, $mEnd])
                ->sum('amount');

            $cashflowTrends[] = [
                'month' => $monthLabel,
                'income' => $mIncome,
                'expense' => $mExpense
            ];
        }

        // 6. Category spending breakdown for Doughnut chart
        $spendingCategories = $user->categories()
            ->where('type', 'expense')
            ->get()
            ->map(function ($category) use ($startDate, $endDate) {
                $spent = (float) $category->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                return [
                    'name' => $category->name,
                    'color' => $category->color ?? '#6366f1',
                    'value' => $spent
                ];
            })
            ->filter(fn($c) => $c['value'] > 0)
            ->values();

        // 7. Mock contacts for Quick Send
        $mockContacts = [
            ['id' => 1, 'name' => 'Musharof Chy', 'initials' => 'MC', 'color' => '#3b82f6'],
            ['id' => 2, 'name' => 'Naimur Rahman', 'initials' => 'NR', 'color' => '#10b981'],
            ['id' => 3, 'name' => 'Shafiqul Islam', 'initials' => 'SI', 'color' => '#f59e0b'],
            ['id' => 4, 'name' => 'Liton Das', 'initials' => 'LD', 'color' => '#ef4444'],
            ['id' => 5, 'name' => 'Sakib Al Hasan', 'initials' => 'SH', 'color' => '#ec4899'],
            ['id' => 6, 'name' => 'Tamim Iqbal', 'initials' => 'TI', 'color' => '#8b5cf6'],
            ['id' => 7, 'name' => 'Mustafiz Rahman', 'initials' => 'MR', 'color' => '#06b6d4'],
            ['id' => 8, 'name' => 'Taskin Ahmed', 'initials' => 'TA', 'color' => '#14b8a6'],
        ];

        // 8. Recent transactions (Current month's transactions)
        $recentTransactions = $user->transactions()
            ->with(['category', 'account'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'description' => $t->description ?? 'Transaction',
                    'amount' => (float) $t->amount,
                    'type' => $t->type,
                    'date' => $t->transaction_date ? $t->transaction_date->format('M d, Y') : null,
                    'category_name' => $t->category ? $t->category->name : 'Uncategorized',
                    'category_color' => $t->category ? $t->category->color : '#94a3b8',
                    'account_name' => $t->account ? $t->account->name : 'N/A',
                ];
            });

        // 9. Recurring reminders
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

        $month = Carbon::now()->format('Y-m');
        $categoriesData = $user->categories()
            ->get()
            ->map(function ($category) use ($startDate, $endDate, $month) {
                $spent = (float) $category->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                $earned = (float) $category->transactions()
                    ->where('type', 'income')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

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
                    'spent' => $spent,
                    'earned' => $earned,
                    'budget_limit' => $limit,
                    'percentage_used' => $percentage,
                    'deficit' => $deficit,
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

        return Inertia::render('Reports/Finance', [
            'summary' => [
                'total_balance' => $totalBalance,
                'monthly_income' => $inflow,
                'monthly_expense' => $outflow,
                'savings_rate' => $savingsRate,
                'income_change_pct' => $incomeChangePct,
                'spent_change_pct' => $spentChangePct,
            ],
            'stats' => [
                'net_balance' => $totalBalance,
                'monthly_income' => $inflow,
                'monthly_expenses' => $outflow,
            ],
            'sparkline' => $sparklineData,
            'cashflow' => $cashflowTrends,
            'spending_categories' => $spendingCategories,
            'accounts' => $accounts,
            'contacts' => $mockContacts,
            'recent_transactions' => $recentTransactions,
            'reminders' => $reminders,
            'categories' => $categoriesData,
            'current_month' => $month,
            'daily_summary' => $dailySummary,
        ]);
    }

    public function daily(Request $request): Response
    {
        $user = Auth::user();
        
        // Parse date from request or default to today
        $dateStr = $request->input('date', Carbon::now()->format('Y-m-d'));
        try {
            $date = Carbon::parse($dateStr);
        } catch (\Exception $e) {
            $date = Carbon::now();
            $dateStr = $date->format('Y-m-d');
        }

        $daysInMonth = $date->daysInMonth;
        $dayOfMonth = $date->day;

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
            $startOfMonth = $date->copy()->startOfMonth();
            $yesterdayEnd = $date->copy()->subDay()->endOfDay();

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

        // Fetch actuals for that specific date
        $actualExpense = (float) $user->transactions()
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->where(function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('expense_occurrence', 'daily');
                })->orWhereNull('category_id');
            })
            ->whereDate('transaction_date', $dateStr)
            ->sum('amount');

        $surplus = round($dailyExpenseBudget - $actualExpense, 2);

        // Calculate cumulative savings up to selected date
        $startOfMonth = $date->copy()->startOfMonth();
        $todayEnd = $date->copy()->endOfDay();
        
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

        // Fetch transactions list for that day (expenses only, excluding one-time monthly expenses and transfers)
        $transactions = $user->transactions()
            ->with(['category', 'account'])
            ->where('type', 'expense')
            ->where('is_transfer', false)
            ->where(function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('expense_occurrence', 'daily');
                })->orWhereNull('category_id');
            })
            ->whereDate('transaction_date', $dateStr)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'amount' => (float) $t->amount,
                    'type' => $t->type,
                    'description' => $t->description,
                    'category_name' => $t->category?->name ?? 'Uncategorized',
                    'category_color' => $t->category?->color ?? '#94A3B8',
                    'account_name' => $t->account?->name ?? 'Unknown',
                    'transaction_date' => $t->transaction_date,
                    'expense_occurrence' => $t->category?->expense_occurrence ?? 'daily',
                ];
            });

        $dailyCategories = $user->categories()
            ->where('type', 'expense')
            ->where('expense_occurrence', 'daily')
            ->pluck('name')
            ->toArray();

        $weeklyCategories = $user->categories()
            ->where('type', 'expense')
            ->where('expense_occurrence', 'weekly_one_time')
            ->pluck('name')
            ->toArray();

        $oneTimeCategories = $user->categories()
            ->where('type', 'expense')
            ->where('expense_occurrence', 'one_time')
            ->pluck('name')
            ->toArray();

        $dailyCategorySummary = $user->categories()
            ->where('type', 'expense')
            ->where('expense_occurrence', 'daily')
            ->get()
            ->map(function ($category) use ($startOfMonth, $todayEnd) {
                $budget = $category->budgets()->whereNull('month')->first();
                $limit = $budget ? (float) $budget->amount : 0.0;

                $spent = (float) $category->transactions()
                    ->where('type', 'expense')
                    ->where('is_transfer', false)
                    ->whereBetween('transaction_date', [$startOfMonth, $todayEnd])
                    ->sum('amount');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color ?? '#3B82F6',
                    'budget' => $limit,
                    'spent' => $spent,
                    'remaining' => round($limit - $spent, 2),
                ];
            });

        return Inertia::render('Reports/Daily', [
            'date' => $dateStr,
            'budget' => [
                'expense' => $dailyExpenseBudget,
            ],
            'actual' => [
                'expense' => $actualExpense,
            ],
            'surplus' => $surplus,
            'cumulative_savings' => $cumulativeSavings,
            'transactions' => $transactions,
            'daily_categories' => $dailyCategories,
            'weekly_categories' => $weeklyCategories,
            'one_time_categories' => $oneTimeCategories,
            'monthly_daily_summary' => [
                'budget' => $expenseBudgetTotal,
                'actual' => $actualExpenseCumulative,
                'remaining' => round($expenseBudgetTotal - $actualExpenseCumulative, 2),
            ],
            'daily_category_summary' => $dailyCategorySummary,
        ]);
    }
}
