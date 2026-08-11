<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        $categories = $user->categories()->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'type' => $cat->type,
                'color' => $cat->color,
            ];
        });

        $accounts = $user->accounts()->get()->map(function ($acc) {
            $totalIncome = $acc->transactions()->where('type', 'income')->sum('amount');
            $totalExpense = $acc->transactions()->where('type', 'expense')->sum('amount');
            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'type' => $acc->type,
                'color' => $acc->color ?? '#6366f1',
                'current_balance' => round((float) $acc->initial_balance + (float) $totalIncome - (float) $totalExpense, 2),
            ];
        });

        // Date range filters (default to current month)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Filter and get transactions
        $query = $user->transactions()
            ->with(['category', 'account', 'transferTransaction.account'])
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($request->filled('type')) {
            if ($request->type === 'transfer') {
                $query->where('is_transfer', true);
            } else {
                $query->where('type', $request->type)->where('is_transfer', false);
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Calculate stats on the query *before* pagination
        $incomeQuery = clone $query;
        $expenseQuery = clone $query;

        $totalIncome = (float) $incomeQuery->where('type', 'income')->where('is_transfer', false)->sum('amount');
        $totalExpense = (float) $expenseQuery->where('type', 'expense')->where('is_transfer', false)->sum('amount');

        $transactionsData = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Transaction $tx) {
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
                        'type' => $tx->category->type,
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

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactionsData,
            'categories' => $categories,
            'accounts' => $accounts,
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', ''),
                'category_id' => $request->input('category_id', ''),
                'account_id' => $request->input('account_id', ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stats' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'net' => $totalIncome - $totalExpense,
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        Auth::user()->transactions()->create($request->validated());

        return redirect()->back();
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== Auth::id()) {
            abort(403);
        }

        $transaction->update($request->validated());

        return redirect()->back();
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== Auth::id()) {
            abort(403);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction) {
            if ($transaction->is_transfer && $transaction->transfer_transaction_id) {
                $linked = Transaction::find($transaction->transfer_transaction_id);
                if ($linked) {
                    $linked->update(['transfer_transaction_id' => null]);
                    $linked->delete();
                }
            }

            $loanId = $transaction->loan_id;

            $transaction->delete();

            if ($loanId) {
                $loan = \App\Models\Loan::find($loanId);
                if ($loan) {
                    $repaymentSum = $loan->transactions()
                        ->where('type', $loan->type === 'lent' ? 'income' : 'expense')
                        ->sum('amount');

                    $loan->update([
                        'status' => $repaymentSum >= $loan->amount ? 'repaid' : 'active',
                    ]);
                }
            }
        });

        return redirect()->back();
    }
}
