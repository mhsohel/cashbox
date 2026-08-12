<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'module_permissions' => [
                'ledger' => true,
                'budgets' => true,
                'licenses' => false,
                'loans' => true,
                'recurring' => true,
            ],
        ]);

        // Seed basic categories of income and expenses
        $defaultCategories = [
            ['name' => 'Salary', 'type' => 'income', 'color' => '#10b981', 'expense_occurrence' => 'daily'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#3b82f6', 'expense_occurrence' => 'daily'],
            ['name' => 'Investments', 'type' => 'income', 'color' => '#8b5cf6', 'expense_occurrence' => 'daily'],
            ['name' => 'Other Income', 'type' => 'income', 'color' => '#f59e0b', 'expense_occurrence' => 'daily'],
            ['name' => 'Food & Dining', 'type' => 'expense', 'color' => '#ef4444', 'expense_occurrence' => 'daily'],
            ['name' => 'Rent & Utilities', 'type' => 'expense', 'color' => '#f97316', 'expense_occurrence' => 'one_time'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#ec4899', 'expense_occurrence' => 'daily'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#a855f7', 'expense_occurrence' => 'daily'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#06b6d4', 'expense_occurrence' => 'daily'],
            ['name' => 'Medical', 'type' => 'expense', 'color' => '#14b8a6', 'expense_occurrence' => 'daily'],
        ];

        foreach ($defaultCategories as $categoryData) {
            \App\Models\Category::create(array_merge($categoryData, ['user_id' => $user->id]));
        }

        // Seed default Cash account
        \App\Models\Account::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => 0.00,
            'color' => '#4f46e5',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
