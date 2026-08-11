<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        DB::transaction(function () use ($validated) {
            $user = Auth::user();

            // 1. Create source transaction (Expense)
            $expenseTx = $user->transactions()->create([
                'account_id' => $validated['from_account_id'],
                'amount' => $validated['amount'],
                'type' => 'expense',
                'transaction_date' => $validated['transaction_date'],
                'description' => $validated['description'] ?? 'Fund Transfer',
                'is_transfer' => true,
            ]);

            // 2. Create destination transaction (Income)
            $incomeTx = $user->transactions()->create([
                'account_id' => $validated['to_account_id'],
                'amount' => $validated['amount'],
                'type' => 'income',
                'transaction_date' => $validated['transaction_date'],
                'description' => $validated['description'] ?? 'Fund Transfer',
                'is_transfer' => true,
            ]);

            // 3. Link them together
            $expenseTx->update(['transfer_transaction_id' => $incomeTx->id]);
            $incomeTx->update(['transfer_transaction_id' => $expenseTx->id]);
        });

        return redirect()->back();
    }

    public function update(StoreTransferRequest $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$transaction->is_transfer || !$transaction->transfer_transaction_id) {
            abort(400, 'Transaction is not a valid transfer.');
        }

        $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);
        if (!$linkedTransaction || $linkedTransaction->user_id !== Auth::id()) {
            abort(400, 'Linked transfer transaction not found.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($transaction, $linkedTransaction, $validated) {
            // Determine which one is source/expense and which one is destination/income
            $expenseTx = $transaction->type === 'expense' ? $transaction : $linkedTransaction;
            $incomeTx = $transaction->type === 'income' ? $transaction : $linkedTransaction;

            // Update source/expense transaction details
            $expenseTx->update([
                'account_id' => $validated['from_account_id'],
                'amount' => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
                'description' => $validated['description'] ?? 'Fund Transfer',
            ]);

            // Update destination/income transaction details
            $incomeTx->update([
                'account_id' => $validated['to_account_id'],
                'amount' => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
                'description' => $validated['description'] ?? 'Fund Transfer',
            ]);
        });

        return redirect()->back();
    }
}
