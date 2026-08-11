<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetMonitorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        // Default to current month if not specified
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        // Fetch categories with budget limits and actual spent/earned
        $categoriesData = $user->categories()
            ->get()
            ->map(function (Category $category) use ($startDate, $endDate, $month) {
                // Sum of expenses for this category in the month
                $spent = $category->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');

                // Sum of income for this category in the month
                $earned = $category->transactions()
                    ->where('type', 'income')
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
                ];
            });

        return Inertia::render('Budgets/Index', [
            'categories' => $categoriesData,
            'current_month' => $month,
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $category = Category::findOrFail($validated['category_id']);
        $month = $category->type === 'income' ? ($validated['month'] ?? now()->format('Y-m')) : null;

        Auth::user()->budgets()->updateOrCreate(
            [
                'category_id' => $validated['category_id'],
                'month' => $month,
            ],
            [
                'amount' => $validated['amount'],
            ]
        );

        return redirect()->back();
    }

    public function monitor(BudgetMonitorService $monitorService): Response
    {
        $user = Auth::user();
        $insights = $monitorService->getBudgetInsights($user);

        return Inertia::render('Finance/GlobalBudget', $insights);
    }
}

