<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    categories: Array,
    current_month: String
});

// State
const activeBudgetTab = ref('expense');
const showCategoryModal = ref(false);
const showBudgetModal = ref(false);
const selectedCategoryForBudget = ref(null);
const selectedMonth = ref(props.current_month);

// Forms
const categoryForm = useForm({
    name: '',
    type: 'expense',
    color: '#3B82F6',
});

const budgetForm = useForm({
    category_id: '',
    amount: '',
    month: '',
});

// Utility: format currency
const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

// Filtered Lists
const expenseCategories = computed(() => {
    return props.categories.filter(c => c.type === 'expense');
});

const incomeCategories = computed(() => {
    return props.categories.filter(c => c.type === 'income');
});

// Totals
const totalBudgetLimit = computed(() => {
    return expenseCategories.value.reduce((sum, c) => sum + c.budget_limit, 0);
});

const totalBudgetSpent = computed(() => {
    return expenseCategories.value.reduce((sum, c) => sum + c.spent, 0);
});

const totalIncomeTargetLimit = computed(() => {
    return incomeCategories.value.reduce((sum, c) => sum + c.budget_limit, 0);
});

const totalIncomeTargetEarned = computed(() => {
    return incomeCategories.value.reduce((sum, c) => sum + c.earned, 0);
});

// Actions
const handleMonthChange = () => {
    router.get(route('budgets.index'), { month: selectedMonth.value }, { preserveState: true });
};

const prevMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number);
    const prevDate = new Date(year, month - 2, 1);
    const y = prevDate.getFullYear();
    const m = String(prevDate.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${y}-${m}`;
    handleMonthChange();
};

const nextMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number);
    const nextDate = new Date(year, month, 1);
    const y = nextDate.getFullYear();
    const m = String(nextDate.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${y}-${m}`;
    handleMonthChange();
};

const openCategoryModal = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.type = activeBudgetTab.value;
    showCategoryModal.value = true;
};

const submitCategory = () => {
    categoryForm.post(route('categories.store'), {
        onSuccess: () => {
            showCategoryModal.value = false;
            categoryForm.reset();
        },
    });
};

const deleteCategory = (id) => {
    if (confirm('Are you sure you want to delete this category? All transactions linked to it will be uncategorized.')) {
        router.delete(route('categories.destroy', id));
    }
};

const openBudgetModal = (category) => {
    selectedCategoryForBudget.value = category;
    budgetForm.category_id = category.id;
    budgetForm.amount = category.budget_limit || '';
    budgetForm.month = category.type === 'income' ? selectedMonth.value : '';
    budgetForm.clearErrors();
    showBudgetModal.value = true;
};

const submitBudget = () => {
    budgetForm.post(route('budgets.store'), {
        onSuccess: () => {
            showBudgetModal.value = false;
            budgetForm.reset();
        },
    });
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
    <Head title="Budget Targets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        Smart Budget Targets
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Configure envelope limits, saving targets, and monitor monthly allocation limits.
                    </p>
                </div>

                <!-- Month Navigation -->
                <div class="flex items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-1.5 shadow-sm">
                    <button 
                        @click="prevMonth"
                        class="p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-600 dark:text-slate-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <input 
                        type="month" 
                        v-model="selectedMonth" 
                        @change="handleMonthChange"
                        class="bg-transparent border-0 text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-0 py-0 px-2 cursor-pointer w-28 text-center"
                    />
                    <button 
                        @click="nextMonth"
                        class="p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-600 dark:text-slate-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider">
                            Allocations Summary
                        </h3>
                    </div>
                    <button 
                        @click="openCategoryModal"
                        class="h-9 px-4 bg-indigo-650 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Category
                    </button>
                </div>

                <!-- Tab switcher -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800 mb-6 gap-4">
                    <div class="flex gap-2">
                        <button 
                            type="button"
                            @click="activeBudgetTab = 'expense'"
                            class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all -mb-[1px]"
                            :class="activeBudgetTab === 'expense' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        >
                            Expense Budgets
                        </button>
                        <button 
                            type="button"
                            @click="activeBudgetTab = 'income'"
                            class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all -mb-[1px]"
                            :class="activeBudgetTab === 'income' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        >
                            Income Targets
                        </button>
                    </div>
                    <div class="pb-2 flex items-center gap-1.5 text-xs font-bold">
                        <span class="text-slate-650 dark:text-slate-400 uppercase tracking-wider text-[10px]">Projected Savings Target:</span>
                        <span class="text-sm font-black" :class="(totalIncomeTargetLimit - totalBudgetLimit) >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ (totalIncomeTargetLimit - totalBudgetLimit) >= 0 ? '+' : '' }}{{ formatCurrency(totalIncomeTargetLimit - totalBudgetLimit) }}
                        </span>
                    </div>
                </div>

                <!-- Expense Tab Content -->
                <div v-if="activeBudgetTab === 'expense'" class="space-y-6">
                    <div v-if="totalBudgetLimit > 0" class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100/50 dark:border-indigo-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <p class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Monthly Budget Limit</p>
                            <h4 class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ formatCurrency(totalBudgetLimit) }}</h4>
                        </div>
                        <div class="sm:text-right space-y-1">
                            <p class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Spent on Budgeted Categories</p>
                            <h4 class="text-xl font-black text-slate-800 dark:text-slate-200" :class="totalBudgetSpent > totalBudgetLimit ? 'text-rose-600 dark:text-rose-400' : ''">
                                {{ formatCurrency(totalBudgetSpent) }}
                                <span class="text-xs text-slate-400 font-semibold">({{ ((totalBudgetSpent / totalBudgetLimit) * 100).toFixed(0) }}% Used)</span>
                            </h4>
                        </div>
                    </div>

                    <div v-if="expenseCategories.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                        No expense categories registered yet.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div 
                            v-for="cat in expenseCategories" 
                            :key="cat.id"
                            class="p-5 rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/40 flex flex-col justify-between hover:shadow-sm transition-all"
                        >
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ cat.name }}</h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="openBudgetModal(cat)"
                                        class="p-1.5 hover:bg-slate-150 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                        title="Edit Budget Limit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button 
                                        @click="deleteCategory(cat.id)"
                                        class="p-1.5 hover:bg-slate-150 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                        title="Delete Category"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div v-if="cat.budget_limit > 0" class="space-y-2">
                                <div class="flex justify-between text-xs text-slate-655 dark:text-slate-400">
                                    <span class="font-bold">{{ formatCurrency(cat.spent) }} spent</span>
                                    <span>Limit: {{ formatCurrency(cat.budget_limit) }}</span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500" 
                                        :class="cat.percentage_used > 90 ? 'bg-rose-500' : (cat.percentage_used >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                                        :style="{ width: Math.min(cat.percentage_used, 100) + '%' }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                                    <span>{{ cat.percentage_used }}% Limit Used</span>
                                    <span :class="cat.spent > cat.budget_limit ? 'text-rose-500 font-extrabold' : 'text-emerald-500'">
                                        {{ cat.spent > cat.budget_limit ? 'Over limit by ' + formatCurrency(cat.spent - cat.budget_limit) : formatCurrency(cat.budget_limit - cat.spent) + ' left' }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500 dark:text-slate-600 italic">
                                No limit configured. Total Spent: <span class="font-bold text-slate-700 dark:text-slate-400">{{ formatCurrency(cat.spent) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Income Tab Content -->
                <div v-if="activeBudgetTab === 'income'" class="space-y-6">
                    <div v-if="totalIncomeTargetLimit > 0" class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100/50 dark:border-emerald-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <p class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Monthly Income Target</p>
                            <h4 class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(totalIncomeTargetLimit) }}</h4>
                        </div>
                        <div class="sm:text-right space-y-1">
                            <p class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Income Earned (Budgeted)</p>
                            <h4 class="text-xl font-black text-slate-800 dark:text-slate-200">
                                {{ formatCurrency(totalIncomeTargetEarned) }}
                                <span class="text-xs text-slate-400 font-semibold">({{ ((totalIncomeTargetEarned / totalIncomeTargetLimit) * 100).toFixed(0) }}% Achieved)</span>
                            </h4>
                        </div>
                    </div>

                    <div v-if="incomeCategories.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                        No income categories registered yet.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div 
                            v-for="cat in incomeCategories" 
                            :key="cat.id"
                            class="p-5 rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/40 flex flex-col justify-between hover:shadow-sm transition-all"
                        >
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ cat.name }}</h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="openBudgetModal(cat)"
                                        class="p-1.5 hover:bg-slate-150 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                        title="Edit Target Limit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button 
                                        @click="deleteCategory(cat.id)"
                                        class="p-1.5 hover:bg-slate-150 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                        title="Delete Category"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div v-if="cat.budget_limit > 0" class="space-y-2">
                                <div class="flex justify-between text-xs text-slate-655 dark:text-slate-400">
                                    <span class="font-bold">{{ formatCurrency(cat.earned) }} earned</span>
                                    <span>Target: {{ formatCurrency(cat.budget_limit) }}</span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500" 
                                        :class="cat.earned >= cat.budget_limit ? 'bg-emerald-500' : 'bg-indigo-500'"
                                        :style="{ width: Math.min(cat.percentage_used, 100) + '%' }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                                    <span>{{ cat.percentage_used }}% Target Achieved</span>
                                    <span :class="cat.earned >= cat.budget_limit ? 'text-emerald-500 font-extrabold' : 'text-rose-500'">
                                        {{ cat.earned >= cat.budget_limit ? 'Target met!' : formatCurrency(cat.deficit) + ' deficit' }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500 dark:text-slate-600 italic">
                                No target configured. Total Earned: <span class="font-bold text-slate-700 dark:text-slate-400">{{ formatCurrency(cat.earned) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Add Category Modal -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative">
                <button 
                    @click="showCategoryModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-base font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4">
                    Add Category
                </h3>

                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Category Name
                        </label>
                        <input 
                            type="text" 
                            v-model="categoryForm.name" 
                            placeholder="e.g. Groceries, Dividends..."
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="categoryForm.errors.name" class="text-xs text-rose-500 mt-1 font-semibold">{{ categoryForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Category Type
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <button 
                                type="button" 
                                @click="categoryForm.type = 'expense'"
                                :class="[
                                    'h-11 rounded-xl text-xs font-bold border transition-all flex items-center justify-center gap-2',
                                    categoryForm.type === 'expense' ? 'bg-indigo-650 hover:bg-indigo-700 text-white border-transparent shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                Expense
                            </button>
                            <button 
                                type="button" 
                                @click="categoryForm.type = 'income'"
                                :class="[
                                    'h-11 rounded-xl text-xs font-bold border transition-all flex items-center justify-center gap-2',
                                    categoryForm.type === 'income' ? 'bg-indigo-650 hover:bg-indigo-700 text-white border-transparent shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                Income
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Visual Tag Color
                        </label>
                        <div class="flex flex-wrap gap-2.5">
                            <button 
                                v-for="c in colors" 
                                :key="c"
                                type="button"
                                @click="categoryForm.color = c"
                                class="w-8 h-8 rounded-full border-2 transition-all hover:scale-110"
                                :class="categoryForm.color === c ? 'border-slate-900 dark:border-white scale-105' : 'border-transparent opacity-85'"
                                :style="{ backgroundColor: c }"
                            ></button>
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="categoryForm.processing"
                        class="w-full h-11 bg-indigo-700 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        Create Category
                    </button>
                </form>
            </div>
        </div>

        <!-- Edit Budget/Target Modal -->
        <div v-if="showBudgetModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative">
                <button 
                    @click="showBudgetModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-base font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4">
                    {{ selectedCategoryForBudget?.type === 'income' ? 'Set Monthly Income Target' : 'Set Monthly Expense Limit' }}
                </h3>
                <p class="text-xs text-slate-500 mb-4 font-semibold">
                    Category: <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ selectedCategoryForBudget?.name }}</span>
                </p>

                <form @submit.prevent="submitBudget" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Target Amount Limit (৳)
                        </label>
                        <input 
                            type="number" 
                            v-model="budgetForm.amount" 
                            placeholder="0.00"
                            step="0.01"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="budgetForm.errors.amount" class="text-xs text-rose-500 mt-1 font-semibold">{{ budgetForm.errors.amount }}</p>
                    </div>

                    <!-- Month (only shown if type === 'income') -->
                    <div v-if="selectedCategoryForBudget?.type === 'income'">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Target Month
                        </label>
                        <input 
                            type="month" 
                            v-model="budgetForm.month"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                            required
                        />
                        <p v-if="budgetForm.errors.month" class="text-xs text-rose-500 mt-1 font-semibold">{{ budgetForm.errors.month }}</p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="budgetForm.processing"
                        class="w-full h-11 bg-indigo-700 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        Save Budget Target
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
