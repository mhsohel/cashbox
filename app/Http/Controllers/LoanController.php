<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\StoreRepaymentRequest;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. Calculate active and resolved loans
        $loans = $user->loans()->get()->map(function (Loan $loan) {
            $repaymentSum = $loan->transactions()
                ->where('type', $loan->type === 'lent' ? 'income' : 'expense')
                ->sum('amount');

            $outstanding = $loan->amount - $repaymentSum;

            return [
                'id' => $loan->id,
                'person_name' => $loan->person_name,
                'type' => $loan->type,
                'amount' => (float) $loan->amount,
                'due_date' => $loan->due_date ? $loan->due_date->format('Y-m-d') : null,
                'description' => $loan->description,
                'status' => $loan->status,
                'outstanding_balance' => (float) round($outstanding, 2),
                'is_overdue' => $loan->status === 'active' && $loan->due_date && $loan->due_date->lt(Carbon::today()),
            ];
        });

        // 2. Compute statistics
        $totalReceivable = 0;
        $totalPayable = 0;
        $overdueCount = 0;
        $today = Carbon::today();

        foreach ($loans as $l) {
            if ($l['status'] === 'active') {
                if ($l['type'] === 'lent') {
                    $totalReceivable += $l['outstanding_balance'];
                } else {
                    $totalPayable += $l['outstanding_balance'];
                }

                if ($l['due_date'] && Carbon::parse($l['due_date'])->lt($today)) {
                    $overdueCount++;
                }
            }
        }

        // 3. Get all repayments history
        $repayments = Transaction::with(['account', 'loan'])
            ->where('user_id', $user->id)
            ->whereNotNull('loan_id')
            ->where(function ($query) {
                $query->whereHas('loan', function ($q) {
                    $q->where('type', 'lent');
                })->where('type', 'income')
                ->orWhereHas('loan', function ($q) {
                    $q->where('type', 'borrowed');
                })->where('type', 'expense');
            })
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Transaction $tx) {
                return [
                    'id' => $tx->id,
                    'loan_id' => $tx->loan_id,
                    'loan_person_name' => $tx->loan->person_name,
                    'loan_type' => $tx->loan->type,
                    'amount' => (float) $tx->amount,
                    'transaction_date' => $tx->transaction_date->format('Y-m-d'),
                    'description' => $tx->description,
                    'account' => $tx->account ? [
                        'id' => $tx->account->id,
                        'name' => $tx->account->name,
                        'color' => $tx->account->color,
                    ] : null,
                ];
            });

        // 4. Sourced accounts for payment funding
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

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'repayments' => $repayments,
            'accounts' => $accountsData,
            'stats' => [
                'total_receivable' => (float) round($totalReceivable, 2),
                'total_payable' => (float) round($totalPayable, 2),
                'overdue_count' => $overdueCount,
            ],
        ]);
    }

    public function store(StoreLoanRequest $request): RedirectResponse
    {
        $user = Auth::user();

        DB::transaction(function () use ($user, $request) {
            $loan = $user->loans()->create([
                'person_name' => $request->person_name,
                'type' => $request->type,
                'amount' => $request->amount,
                'due_date' => $request->due_date,
                'description' => $request->description,
                'status' => 'active',
            ]);

            if ($request->filled('account_id')) {
                $user->transactions()->create([
                    'account_id' => $request->account_id,
                    'loan_id' => $loan->id,
                    'amount' => $request->amount,
                    'type' => $request->type === 'lent' ? 'expense' : 'income',
                    'transaction_date' => now()->format('Y-m-d'),
                    'description' => $request->type === 'lent'
                        ? "Lent money to {$request->person_name}"
                        : "Borrowed money from {$request->person_name}",
                ]);
            }
        });

        return redirect()->back();
    }

    public function logRepayment(StoreRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        if ($loan->user_id !== Auth::id()) {
            abort(403);
        }

        DB::transaction(function () use ($loan, $request) {
            // Log repayment transaction
            $loan->transactions()->create([
                'user_id' => $loan->user_id,
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'type' => $loan->type === 'lent' ? 'income' : 'expense',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description ?? "Repayment for loan of {$loan->person_name}",
            ]);

            // Check outstanding balance
            $repaymentSum = $loan->transactions()
                ->where('type', $loan->type === 'lent' ? 'income' : 'expense')
                ->sum('amount');

            if ($repaymentSum >= $loan->amount) {
                $loan->update(['status' => 'repaid']);
            } else {
                $loan->update(['status' => 'active']);
            }
        });

        return redirect()->back();
    }

    public function destroy(Loan $loan): RedirectResponse
    {
        if ($loan->user_id !== Auth::id()) {
            abort(403);
        }

        $loan->delete();

        return redirect()->back();
    }
}
