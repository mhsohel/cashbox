<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    categories: Array
});

// State
const activeTab = ref('expense'); // 'expense' or 'income'
const showCategoryModal = ref(false);
const isEditingCategory = ref(false);

const categoryForm = useForm({
    id: null,
    name: '',
    type: 'expense',
    color: '#3B82F6',
    expense_occurrence: 'daily',
});

// Computations
const expenseCategories = computed(() => {
    return props.categories.filter(c => c.type === 'expense');
});

const incomeCategories = computed(() => {
    return props.categories.filter(c => c.type === 'income');
});

const currentCategories = computed(() => {
    return activeTab.value === 'expense' ? expenseCategories.value : incomeCategories.value;
});

// Actions
const openAddCategory = () => {
    isEditingCategory.value = false;
    categoryForm.reset();
    categoryForm.type = activeTab.value;
    categoryForm.color = '#3B82F6';
    categoryForm.expense_occurrence = 'daily';
    categoryForm.clearErrors();
    showCategoryModal.value = true;
};

const openEditCategory = (cat) => {
    isEditingCategory.value = true;
    categoryForm.id = cat.id;
    categoryForm.name = cat.name;
    categoryForm.type = cat.type;
    categoryForm.color = cat.color || '#3B82F6';
    categoryForm.expense_occurrence = cat.expense_occurrence || 'daily';
    categoryForm.clearErrors();
    showCategoryModal.value = true;
};

const submitCategory = () => {
    if (isEditingCategory.value) {
        categoryForm.patch(route('categories.update', categoryForm.id), {
            onSuccess: () => {
                showCategoryModal.value = false;
                categoryForm.reset();
            }
        });
    } else {
        categoryForm.post(route('categories.store'), {
            onSuccess: () => {
                showCategoryModal.value = false;
                categoryForm.reset();
            }
        });
    }
};

const deleteCategory = (cat) => {
    let warningMsg = `Are you sure you want to delete the category "${cat.name}"?`;
    if (cat.transactions_count > 0 || cat.budgets_count > 0) {
        warningMsg += ` This category is linked to ${cat.transactions_count} transactions and ${cat.budgets_count} budgets. Deleting it will leave them uncategorized.`;
    }
    
    if (confirm(warningMsg)) {
        router.delete(route('categories.destroy', cat.id));
    }
};

const colors = [
    '#3B82F6', // Blue
    '#10B981', // Emerald
    '#F59E0B', // Amber
    '#EF4444', // Red
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#06B6D4', // Cyan
    '#14B8A6', // Teal
    '#F43F5E', // Rose
    '#6366F1', // Indigo
];
</script>

<template>
    <Head title="Manage Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        Categories Configuration
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Organize your financial flows. Setup custom categories for income sources and expense targets.
                    </p>
                </div>
                <button 
                    @click="openAddCategory"
                    class="px-4 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Category
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
            <!-- Tabs Navigation -->
            <div class="flex border-b border-slate-200 dark:border-slate-800">
                <button 
                    @click="activeTab = 'expense'"
                    class="px-6 py-3 text-xs font-black uppercase tracking-wider border-b-2 transition-all duration-150"
                    :class="activeTab === 'expense' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
                >
                    Expense Categories ({{ expenseCategories.length }})
                </button>
                <button 
                    @click="activeTab = 'income'"
                    class="px-6 py-3 text-xs font-black uppercase tracking-wider border-b-2 transition-all duration-150"
                    :class="activeTab === 'income' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
                >
                    Income Categories ({{ incomeCategories.length }})
                </button>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider">
                            Active {{ activeTab === 'expense' ? 'Expense' : 'Income' }} Categories
                        </h3>
                    </div>
                </div>

                <div v-if="currentCategories.length === 0" class="py-16 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                    No {{ activeTab === 'expense' ? 'expense' : 'income' }} categories registered yet. Click "Add Category" to get started.
                </div>

                <!-- Grid Layout for Categories -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div 
                        v-for="cat in currentCategories" 
                        :key="cat.id"
                        class="p-5 rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 relative overflow-hidden transition-all duration-300 hover:shadow-md hover:scale-[1.01] flex flex-col justify-between min-h-[140px]"
                        :style="{ borderLeftWidth: '5px', borderLeftColor: cat.color || '#3B82F6' }"
                    >
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-sm font-black text-slate-805 dark:text-white truncate pr-2" :title="cat.name">
                                    {{ cat.name }}
                                </h4>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button 
                                        @click="openEditCategory(cat)"
                                        class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-all flex items-center justify-center"
                                        title="Modify Category"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button 
                                        @click="deleteCategory(cat)"
                                        class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/20 text-slate-400 hover:text-red-650 dark:hover:text-red-400 transition-all flex items-center justify-center"
                                        title="Delete Category"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Meta info: type badge -->
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <span 
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
                                    :class="cat.type === 'expense' 
                                        ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/20 dark:text-rose-400' 
                                        : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'"
                                >
                                    {{ cat.type }}
                                </span>
                                <span 
                                    v-if="cat.type === 'expense'"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
                                    :class="{
                                        'bg-blue-50 text-blue-600 dark:bg-blue-955/20 dark:text-blue-400': cat.expense_occurrence === 'daily',
                                        'bg-purple-50 text-purple-650 dark:bg-purple-955/20 dark:text-purple-400': cat.expense_occurrence === 'weekly_one_time',
                                        'bg-amber-50 text-amber-600 dark:bg-amber-955/20 dark:text-amber-400': cat.expense_occurrence === 'one_time',
                                    }"
                                >
                                    {{ 
                                        cat.expense_occurrence === 'one_time' ? 'Monthly One-time' 
                                        : cat.expense_occurrence === 'weekly_one_time' ? 'Weekly One-time' 
                                        : 'Daily' 
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Counters linked to transaction / budget items -->
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-500 font-bold">
                            <span class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                {{ cat.transactions_count }} Txns
                            </span>
                            <span class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ cat.budgets_count }} Budgets
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Creator/Editor Modal -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
                <button 
                    @click="showCategoryModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-650 dark:hover:text-slate-200 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-base font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4">
                    {{ isEditingCategory ? 'Modify Category' : 'Create Category' }}
                </h3>

                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Category Name
                        </label>
                        <input 
                            type="text" 
                            v-model="categoryForm.name" 
                            placeholder="e.g. Salary, Groceries, Rent..."
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="categoryForm.errors.name" class="text-xs text-rose-500 mt-1 font-semibold">{{ categoryForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Category Flow Type
                        </label>
                        <select 
                            v-model="categoryForm.type"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="expense">Expense Category</option>
                            <option value="income">Income Category</option>
                        </select>
                        <p v-if="categoryForm.errors.type" class="text-xs text-rose-500 mt-1 font-semibold">{{ categoryForm.errors.type }}</p>
                    </div>

                    <div v-if="categoryForm.type === 'expense'">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Expense Classification
                        </label>
                        <select 
                            v-model="categoryForm.expense_occurrence"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="daily">Daily Variable Expense (e.g. food, transport)</option>
                            <option value="weekly_one_time">Weekly One-Time Expense (e.g. groceries, weekly sub)</option>
                            <option value="one_time">Monthly One-Time Expense (e.g. rent, bills)</option>
                        </select>
                        <p v-if="categoryForm.errors.expense_occurrence" class="text-xs text-rose-500 mt-1 font-semibold">{{ categoryForm.errors.expense_occurrence }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Theme Accent Color
                        </label>
                        <div class="flex flex-wrap gap-2.5">
                            <button 
                                v-for="c in colors" 
                                :key="c"
                                type="button"
                                @click="categoryForm.color = c"
                                class="w-8 h-8 rounded-full border-2 transition hover:scale-110"
                                :class="categoryForm.color === c ? 'border-slate-900 dark:border-white scale-105' : 'border-transparent opacity-85'"
                                :style="{ backgroundColor: c }"
                            ></button>
                        </div>
                        <p v-if="categoryForm.errors.color" class="text-xs text-rose-500 mt-1 font-semibold">{{ categoryForm.errors.color }}</p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="categoryForm.processing"
                        class="w-full h-11 bg-indigo-700 hover:bg-indigo-650 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        {{ isEditingCategory ? 'Save Changes' : 'Create Category' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
