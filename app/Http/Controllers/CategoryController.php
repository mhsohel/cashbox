<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Auth::user()->categories()
            ->withCount(['transactions', 'budgets'])
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color ?? '#3B82F6',
                    'transactions_count' => $category->transactions_count,
                    'budgets_count' => $category->budgets_count,
                ];
            });

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Auth::user()->categories()->create($request->validated());

        return redirect()->back();
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        if ($category->user_id !== Auth::id()) {
            abort(403);
        }

        $category->update($request->validated());

        return redirect()->back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->user_id !== Auth::id()) {
            abort(403);
        }

        $category->delete();

        return redirect()->back();
    }
}
