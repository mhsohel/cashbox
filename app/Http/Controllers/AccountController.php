<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        
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

        return Inertia::render('Accounts/Index', [
            'accounts' => $accountsData,
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        Auth::user()->accounts()->create($request->validated());

        return redirect()->back();
    }

    public function update(StoreAccountRequest $request, Account $account): RedirectResponse
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $account->update($request->validated());

        return redirect()->back();
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $account->delete();

        return redirect()->back();
    }
}
