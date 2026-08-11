<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PremiumServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminController extends Controller
{
    /**
     * Display the superadmin dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $users = User::orderBy('name')
            ->get()
            ->map(function (User $user) {
                // Ensure default permissions structure is sent if null
                $perms = $user->module_permissions ?: [
                    'ledger' => true,
                    'budgets' => true,
                    'licenses' => true,
                    'loans' => true,
                    'recurring' => true,
                ];

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_superadmin' => (bool) $user->is_superadmin,
                    'is_banned' => (bool) $user->is_banned,
                    'module_permissions' => $perms,
                    'created_at' => $user->created_at->format('Y-m-d'),
                ];
            });

        // Search & Filters for Service Requests
        $ordersQuery = PremiumServiceOrder::query();

        if ($request->has('status') && $request->input('status') !== '') {
            $ordersQuery->where('status', $request->input('status'));
        }

        if ($request->has('service_type') && $request->input('service_type') !== '') {
            $ordersQuery->where('service_type', $request->input('service_type'));
        }

        if ($request->has('search') && $request->input('search') !== '') {
            $search = $request->input('search');
            $ordersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();

        return Inertia::render('Superadmin/Dashboard', [
            'users' => $users,
            'orders' => $orders,
            'filters' => $request->only(['status', 'service_type', 'search']),
        ]);
    }

    /**
     * Update module permissions for a user.
     */
    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.ledger' => 'required|boolean',
            'permissions.budgets' => 'required|boolean',
            'permissions.licenses' => 'required|boolean',
            'permissions.loans' => 'required|boolean',
            'permissions.recurring' => 'required|boolean',
        ]);

        $user->update([
            'module_permissions' => $validated['permissions'],
        ]);

        return redirect()->back()->with('success', "Module permissions for {$user->name} have been updated.");
    }

    /**
     * Toggle superadmin status for a user.
     */
    public function toggleSuperadmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot toggle your own superadmin status.');
        }

        $validated = $request->validate([
            'is_superadmin' => 'required|boolean',
        ]);

        $user->update([
            'is_superadmin' => $validated['is_superadmin'],
        ]);

        $status = $validated['is_superadmin'] ? 'promoted to Superadmin' : 'demoted to normal user';

        return redirect()->back()->with('success', "User {$user->name} has been {$status}.");
    }

    /**
     * Toggle ban status for a user.
     */
    public function toggleBan(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot ban yourself.');
        }

        $validated = $request->validate([
            'is_banned' => 'required|boolean',
        ]);

        $user->update([
            'is_banned' => $validated['is_banned'],
        ]);

        $status = $validated['is_banned'] ? 'banned' : 'unbanned';

        return redirect()->back()->with('success', "User {$user->name} has been {$status}.");
    }

    /**
     * Delete a user.
     */
    public function deleteUser(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->back()->with('success', "User {$userName} has been deleted.");
    }
}
