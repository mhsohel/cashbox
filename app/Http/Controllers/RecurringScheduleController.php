<?php

namespace App\Http\Controllers;

use App\Models\RecurringSchedule;
use App\Models\Transaction;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecurringScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $schedules = $user->recurringSchedules()
            ->with(['account', 'category', 'loan'])
            ->orderBy('next_due_date', 'asc')
            ->get()
            ->map(function (RecurringSchedule $schedule) {
                return [
                    'id' => $schedule->id,
                    'type' => $schedule->type,
                    'frequency' => $schedule->frequency,
                    'start_date' => $schedule->start_date->format('Y-m-d'),
                    'next_due_date' => $schedule->next_due_date->format('Y-m-d'),
                    'last_run_date' => $schedule->last_run_date ? $schedule->last_run_date->format('Y-m-d') : null,
                    'is_active' => $schedule->is_active,
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
                ];
            });

        $categories = $user->categories()->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'type' => $cat->type,
                'color' => $cat->color,
            ];
        });

        $accounts = $user->accounts()->get()->map(function ($acc) {
            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'color' => $acc->color,
            ];
        });

        $activeLoans = $user->loans()
            ->where('status', 'active')
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'person_name' => $loan->person_name,
                    'type' => $loan->type,
                    'amount' => (float) $loan->amount,
                ];
            });

        return Inertia::render('Recurring/Index', [
            'schedules' => $schedules,
            'categories' => $categories,
            'accounts' => $accounts,
            'active_loans' => $activeLoans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['expense', 'loan_installment', 'loan'])],
            'frequency' => ['required', 'string', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
            'loan_id' => [
                'nullable',
                Rule::exists('loans', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)->where('status', 'active');
                }),
            ],
            'loan_type' => ['nullable', 'string', Rule::in(['lent', 'borrowed'])],
            'person_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate specific fields based on type
        if ($validated['type'] === 'expense') {
            $request->validate([
                'category_id' => ['required'],
            ]);
        } elseif ($validated['type'] === 'loan_installment') {
            $request->validate([
                'loan_id' => ['required'],
            ]);
        } elseif ($validated['type'] === 'loan') {
            $request->validate([
                'loan_type' => ['required'],
                'person_name' => ['required'],
            ]);
        }

        $startDate = Carbon::parse($validated['start_date']);

        $user->recurringSchedules()->create([
            'type' => $validated['type'],
            'frequency' => $validated['frequency'],
            'amount' => $validated['amount'],
            'start_date' => $startDate,
            'next_due_date' => $startDate,
            'description' => $validated['description'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'loan_id' => $validated['loan_id'] ?? null,
            'loan_type' => $validated['loan_type'] ?? null,
            'person_name' => $validated['person_name'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, RecurringSchedule $recurringSchedule): RedirectResponse
    {
        if ($recurringSchedule->user_id !== Auth::id()) {
            abort(403);
        }

        $user = Auth::user();

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['expense', 'loan_installment', 'loan'])],
            'frequency' => ['required', 'string', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
            'loan_id' => [
                'nullable',
                Rule::exists('loans', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)->where('status', 'active');
                }),
            ],
            'loan_type' => ['nullable', 'string', Rule::in(['lent', 'borrowed'])],
            'person_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['type'] === 'expense') {
            $request->validate([
                'category_id' => ['required'],
            ]);
        } elseif ($validated['type'] === 'loan_installment') {
            $request->validate([
                'loan_id' => ['required'],
            ]);
        } elseif ($validated['type'] === 'loan') {
            $request->validate([
                'loan_type' => ['required'],
                'person_name' => ['required'],
            ]);
        }

        $recurringSchedule->fill([
            'type' => $validated['type'],
            'frequency' => $validated['frequency'],
            'amount' => $validated['amount'],
            'start_date' => Carbon::parse($validated['start_date']),
            'description' => $validated['description'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'loan_id' => $validated['loan_id'] ?? null,
            'loan_type' => $validated['loan_type'] ?? null,
            'person_name' => $validated['person_name'] ?? null,
        ]);

        // If it hasn't run yet, we can safely reset next_due_date to start_date
        if ($recurringSchedule->last_run_date === null) {
            $recurringSchedule->next_due_date = Carbon::parse($validated['start_date']);
        }

        $recurringSchedule->save();

        return redirect()->back();
    }

    public function destroy(RecurringSchedule $recurringSchedule): RedirectResponse
    {
        if ($recurringSchedule->user_id !== Auth::id()) {
            abort(403);
        }

        $recurringSchedule->delete();

        return redirect()->back();
    }

    public function toggle(RecurringSchedule $recurringSchedule): RedirectResponse
    {
        if ($recurringSchedule->user_id !== Auth::id()) {
            abort(403);
        }

        $recurringSchedule->update([
            'is_active' => !$recurringSchedule->is_active
        ]);

        return redirect()->back();
    }

    public function process(RecurringSchedule $recurringSchedule): RedirectResponse
    {
        if ($recurringSchedule->user_id !== Auth::id()) {
            abort(403);
        }

        DB::transaction(function () use ($recurringSchedule) {
            $runDate = $recurringSchedule->next_due_date;

            $runDateString = $runDate->format('Y-m-d');

            if ($recurringSchedule->type === 'expense') {
                Transaction::create([
                    'user_id' => $recurringSchedule->user_id,
                    'category_id' => $recurringSchedule->category_id,
                    'account_id' => $recurringSchedule->account_id,
                    'amount' => $recurringSchedule->amount,
                    'type' => 'expense',
                    'transaction_date' => $runDateString,
                    'description' => $recurringSchedule->description ?? "Recurring expense",
                    'recurring_schedule_id' => $recurringSchedule->id,
                ]);
            } elseif ($recurringSchedule->type === 'loan_installment') {
                $loan = $recurringSchedule->loan;
                if ($loan) {
                    Transaction::create([
                        'user_id' => $recurringSchedule->user_id,
                        'account_id' => $recurringSchedule->account_id,
                        'amount' => $recurringSchedule->amount,
                        'type' => $loan->type === 'lent' ? 'income' : 'expense',
                        'transaction_date' => $runDateString,
                        'description' => $recurringSchedule->description ?? "Repayment installment for {$loan->person_name}",
                        'loan_id' => $loan->id,
                        'recurring_schedule_id' => $recurringSchedule->id,
                    ]);

                    // Recalculate loan status
                    $repaymentSum = $loan->transactions()
                        ->where('type', $loan->type === 'lent' ? 'income' : 'expense')
                        ->sum('amount');

                    $loan->update([
                        'status' => $repaymentSum >= $loan->amount ? 'repaid' : 'active',
                    ]);
                }
            } elseif ($recurringSchedule->type === 'loan') {
                $loan = Loan::create([
                    'user_id' => $recurringSchedule->user_id,
                    'person_name' => $recurringSchedule->person_name,
                    'type' => $recurringSchedule->loan_type,
                    'amount' => $recurringSchedule->amount,
                    'due_date' => $runDate->copy()->addMonth()->format('Y-m-d'), // Default estimate
                    'description' => $recurringSchedule->description ?? "Recurring loan",
                    'status' => 'active',
                    'recurring_schedule_id' => $recurringSchedule->id,
                ]);

                if ($recurringSchedule->account_id) {
                    Transaction::create([
                        'user_id' => $recurringSchedule->user_id,
                        'account_id' => $recurringSchedule->account_id,
                        'loan_id' => $loan->id,
                        'amount' => $recurringSchedule->amount,
                        'type' => $recurringSchedule->loan_type === 'lent' ? 'expense' : 'income',
                        'transaction_date' => $runDateString,
                        'description' => $recurringSchedule->loan_type === 'lent'
                            ? "Lent money to {$recurringSchedule->person_name} (Recurring)"
                            : "Borrowed money from {$recurringSchedule->person_name} (Recurring)",
                        'recurring_schedule_id' => $recurringSchedule->id,
                    ]);
                }
            }

            // Calculate next due date
            $nextDue = $runDate->copy();
            if ($recurringSchedule->frequency === 'weekly') {
                $nextDue->addWeek();
            } elseif ($recurringSchedule->frequency === 'monthly') {
                $nextDue->addMonth();
            } elseif ($recurringSchedule->frequency === 'quarterly') {
                $nextDue->addMonths(3);
            }

            $recurringSchedule->update([
                'last_run_date' => $runDate,
                'next_due_date' => $nextDue,
            ]);
        });

        return redirect()->back();
    }

    public function skip(RecurringSchedule $recurringSchedule): RedirectResponse
    {
        if ($recurringSchedule->user_id !== Auth::id()) {
            abort(403);
        }

        $runDate = $recurringSchedule->next_due_date;
        $nextDue = $runDate->copy();

        if ($recurringSchedule->frequency === 'weekly') {
            $nextDue->addWeek();
        } elseif ($recurringSchedule->frequency === 'monthly') {
            $nextDue->addMonth();
        } elseif ($recurringSchedule->frequency === 'quarterly') {
            $nextDue->addMonths(3);
        }

        $recurringSchedule->update([
            'last_run_date' => $runDate,
            'next_due_date' => $nextDue,
        ]);

        return redirect()->back();
    }
}
