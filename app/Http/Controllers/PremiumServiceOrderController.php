<?php

namespace App\Http\Controllers;

use App\Models\PremiumServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PremiumServiceOrderController extends Controller
{
    /**
     * Display a listing of the premium service orders.
     */
    public function index(Request $request): Response
    {
        $query = PremiumServiceOrder::query();

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('service_type') && $request->input('service_type') !== '') {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->has('search') && $request->input('search') !== '') {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('PremiumServiceOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'service_type', 'search']),
        ]);
    }

    /**
     * Store a newly created premium service order in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'service_type' => 'required|string|max:255',
            'budget' => 'nullable|string|max:255',
            'description' => 'required|string|max:10000',
        ]);

        PremiumServiceOrder::create($validated);

        return redirect()->back()->with('success', 'Your premium service order request has been received successfully!');
    }

    /**
     * Update the specified premium service order in storage.
     */
    public function update(Request $request, PremiumServiceOrder $premiumServiceOrder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,contacted,completed',
        ]);

        $premiumServiceOrder->update($validated);

        return redirect()->back();
    }

    /**
     * Remove the specified premium service order from storage.
     */
    public function destroy(PremiumServiceOrder $premiumServiceOrder): RedirectResponse
    {
        $premiumServiceOrder->delete();

        return redirect()->back();
    }
}
