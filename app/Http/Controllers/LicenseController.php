<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreLicenseRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\License;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $clients = $user->clients()->get();
        
        $licenses = $user->licenses()->with('client')->get()->map(function (License $lic) {
            return [
                'id' => $lic->id,
                'client_id' => $lic->client_id,
                'client_name' => $lic->client->name,
                'saas_name' => $lic->client->saas_name,
                'amount' => (float) $lic->amount,
                'billing_cycle' => $lic->billing_cycle,
                'next_renewal_date' => $lic->next_renewal_date->format('Y-m-d'),
                'status' => $lic->status,
                'last_paid_at' => $lic->last_paid_at ? $lic->last_paid_at->format('Y-m-d') : null,
            ];
        });

        // Calculate MRR and ARR
        $mrr = 0.0;
        foreach ($licenses as $lic) {
            if ($lic['status'] === 'active') {
                if ($lic['billing_cycle'] === 'monthly') {
                    $mrr += $lic['amount'];
                } elseif ($lic['billing_cycle'] === 'yearly') {
                    $mrr += ($lic['amount'] / 12);
                }
            }
        }
        $arr = $mrr * 12;

        // Alerts (due in 7 days or past due)
        $today = Carbon::now()->startOfDay();
        $dueSoonCount = 0;
        foreach ($licenses as $lic) {
            if ($lic['status'] === 'active') {
                $renewal = Carbon::parse($lic['next_renewal_date'])->startOfDay();
                if ($renewal->diffInDays($today, false) >= -7) {
                    $dueSoonCount++;
                }
            }
        }

        $payments = $user->transactions()
            ->whereNotNull('client_id')
            ->with(['client', 'license'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'client_id' => $tx->client_id,
                    'client_name' => $tx->client ? $tx->client->name : 'Unknown Client',
                    'saas_name' => $tx->client ? $tx->client->saas_name : 'Unknown SaaS',
                    'amount' => (float) $tx->amount,
                    'transaction_date' => $tx->transaction_date->format('Y-m-d'),
                    'description' => $tx->description,
                    'billing_cycle' => $tx->license ? $tx->license->billing_cycle : 'monthly',
                ];
            });

        $accounts = $user->accounts()
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
                    'current_balance' => round((float) $account->initial_balance + (float) $totalIncome - (float) $totalExpense, 2),
                ];
            });

        return Inertia::render('Licenses/Index', [
            'clients' => $clients,
            'licenses' => $licenses,
            'payments' => $payments,
            'accounts' => $accounts,
            'stats' => [
                'mrr' => round($mrr, 2),
                'arr' => round($arr, 2),
                'active_count' => $licenses->where('status', 'active')->count(),
                'due_soon_count' => $dueSoonCount,
            ]
        ]);
    }

    public function storeClient(StoreClientRequest $request): RedirectResponse
    {
        Auth::user()->clients()->create($request->validated());

        return redirect()->back();
    }

    public function storeLicense(StoreLicenseRequest $request): RedirectResponse
    {
        Auth::user()->licenses()->create($request->validated());

        return redirect()->back();
    }

    public function destroyClient(Client $client): RedirectResponse
    {
        if ($client->user_id !== Auth::id()) {
            abort(403);
        }

        $client->delete();

        return redirect()->back();
    }

    public function destroyLicense(License $license): RedirectResponse
    {
        if ($license->user_id !== Auth::id()) {
            abort(403);
        }

        $license->delete();

        return redirect()->back();
    }

    public function updateLicense(StoreLicenseRequest $request, License $license): RedirectResponse
    {
        if ($license->user_id !== Auth::id()) {
            abort(403);
        }

        $license->update($request->validated());

        return redirect()->back();
    }

    public function logPayment(Request $request, License $license): RedirectResponse
    {
        if ($license->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                }),
            ],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1005'],
            'advance_renewal' => ['required', 'boolean'],
        ]);

        // 1. Get or Create "SaaS License" Category
        $category = Auth::user()->categories()->firstOrCreate(
            ['name' => 'SaaS License', 'type' => 'income'],
            ['color' => '#8B5CF6'] // Purple
        );

        // 2. Log Transaction
        Auth::user()->transactions()->create([
            'category_id' => $category->id,
            'client_id' => $license->client_id,
            'license_id' => $license->id,
            'account_id' => $validated['account_id'] ?? null,
            'amount' => $validated['amount'],
            'type' => 'income',
            'transaction_date' => $validated['transaction_date'],
            'description' => $validated['description'] ?? "SaaS License Payment - {$license->client->name} ({$license->client->saas_name})",
        ]);

        // 3. Update License dates
        $updateData = [
            'last_paid_at' => $validated['transaction_date'],
        ];

        if ($validated['advance_renewal']) {
            $nextRenewal = Carbon::parse($license->next_renewal_date);
            if ($license->billing_cycle === 'monthly') {
                $nextRenewal->addMonth();
            } else {
                $nextRenewal->addYear();
            }
            $updateData['next_renewal_date'] = $nextRenewal->format('Y-m-d');
        }

        $license->update($updateData);

        return redirect()->back();
    }
}
