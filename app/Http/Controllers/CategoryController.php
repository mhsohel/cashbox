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
                    'expense_occurrence' => $category->expense_occurrence ?? 'daily',
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
        $data = $request->validated();
        if ($data['type'] === 'expense' && empty($data['expense_occurrence'])) {
            $data['expense_occurrence'] = 'daily';
        } elseif ($data['type'] === 'income') {
            $data['expense_occurrence'] = 'daily';
        }
        Auth::user()->categories()->create($data);

        return redirect()->back();
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        if ($category->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validated();
        if ($data['type'] === 'expense' && empty($data['expense_occurrence'])) {
            $data['expense_occurrence'] = 'daily';
        } elseif ($data['type'] === 'income') {
            $data['expense_occurrence'] = 'daily';
        }
        $category->update($data);

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
