<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    daily_summary: {
        type: Object,
        required: false,
    },
    categories: {
        type: Array,
        required: true,
    },
    accounts: {
        type: Array,
        required: true,
    },
    recent_transactions: {
        type: Array,
        required: true,
    },
    current_month: {
        type: String,
        required: true,
    },
    reminders: {
        type: Array,
        default: () => [],
    },
});

// Month Selection
const selectedMonth = ref(props.current_month);
const handleMonthChange = () => {
    router.get(route('dashboard'), { month: selectedMonth.value }, { preserveState: true });
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

// Modals State
const showTransactionModal = ref(false);
const isEditingTransaction = ref(false);
const showCategoryModal = ref(false);
const showBudgetModal = ref(false);
const selectedCategoryForBudget = ref(null);
const activeBudgetTab = ref('expense');
const showAccountModal = ref(false);
const isEditingAccount = ref(false);

// Form States
const transactionForm = useForm({
    id: null,
    amount: '',
    type: 'expense',
    category_id: '',
    account_id: '',
    from_account_id: '',
    to_account_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
});

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

const accountForm = useForm({
    id: null,
    name: '',
    type: 'bank',
    initial_balance: '',
    color: '#6366f1',
});

// Helper for format currency
const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

// Open Transaction Modal for Add
const openAddTransaction = () => {
    isEditingTransaction.value = false;
    transactionForm.reset();
    transactionForm.clearErrors();
    transactionForm.transaction_date = new Date().toISOString().split('T')[0];
    if (props.categories.length > 0) {
        transactionForm.category_id = props.categories[0].id;
    }
    if (props.accounts.length > 0) {
        transactionForm.account_id = props.accounts[0].id;
        transactionForm.from_account_id = props.accounts[0].id;
        if (props.accounts.length > 1) {
            transactionForm.to_account_id = props.accounts[1].id;
        } else {
            transactionForm.to_account_id = '';
        }
    } else {
        transactionForm.account_id = '';
        transactionForm.from_account_id = '';
        transactionForm.to_account_id = '';
    }
    showTransactionModal.value = true;
};

// Open Transaction Modal for Edit
const openEditTransaction = (tx) => {
    isEditingTransaction.value = true;
    transactionForm.id = tx.id;
    transactionForm.amount = tx.amount;
    transactionForm.type = tx.is_transfer ? 'transfer' : tx.type;
    transactionForm.category_id = tx.category ? tx.category.id : '';
    transactionForm.transaction_date = tx.transaction_date;
    transactionForm.description = tx.description || '';
    
    if (tx.is_transfer) {
        if (tx.type === 'expense') {
            transactionForm.from_account_id = tx.account ? tx.account.id : '';
            transactionForm.to_account_id = tx.transfer_account ? tx.transfer_account.id : '';
        } else {
            transactionForm.from_account_id = tx.transfer_account ? tx.transfer_account.id : '';
            transactionForm.to_account_id = tx.account ? tx.account.id : '';
        }
        transactionForm.account_id = '';
    } else {
        transactionForm.account_id = tx.account ? tx.account.id : '';
        transactionForm.from_account_id = '';
        transactionForm.to_account_id = '';
    }
    transactionForm.clearErrors();
    showTransactionModal.value = true;
};

// Submit Transaction
const submitTransaction = () => {
    if (transactionForm.type === 'transfer') {
        if (isEditingTransaction.value) {
            transactionForm.patch(route('transfers.update', transactionForm.id), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                },
            });
        } else {
            transactionForm.post(route('transfers.store'), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                },
            });
        }
    } else {
        if (isEditingTransaction.value) {
            transactionForm.patch(route('transactions.update', transactionForm.id), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                },
            });
        } else {
            transactionForm.post(route('transactions.store'), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                },
            });
        }
    }
};

// Delete Transaction
const deleteTransaction = (id) => {
    if (confirm('Are you sure you want to delete this transaction?')) {
        router.delete(route('transactions.destroy', id));
    }
};

// Open Category Modal
const openCategoryModal = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
    showCategoryModal.value = true;
};

// Submit Category
const submitCategory = () => {
    categoryForm.post(route('categories.store'), {
        onSuccess: () => {
            showCategoryModal.value = false;
            categoryForm.reset();
            // Refill default category selection if empty
            if (!transactionForm.category_id && props.categories.length > 0) {
                transactionForm.category_id = props.categories[props.categories.length - 1].id;
            }
        },
    });
};

// Delete Category
const deleteCategory = (id) => {
    if (confirm('Are you sure you want to delete this category? All transactions linked to it will be un-categorized.')) {
        router.delete(route('categories.destroy', id));
    }
};

// Open Budget Modal
const openBudgetModal = (category) => {
    selectedCategoryForBudget.value = category;
    budgetForm.category_id = category.id;
    budgetForm.amount = category.budget_limit || '';
    budgetForm.month = category.type === 'income' ? props.current_month : '';
    budgetForm.clearErrors();
    showBudgetModal.value = true;
};

// Submit Budget
const submitBudget = () => {
    budgetForm.post(route('budgets.store'), {
        onSuccess: () => {
            showBudgetModal.value = false;
            budgetForm.reset();
        },
    });
};

// Colors palette for Category Creator
const colors = [
    '#3B82F6', // Blue
    '#10B981', // Emerald
    '#F59E0B', // Amber
    '#EF4444', // Red
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#06B6D4', // Cyan
    '#14B8A6', // Teal
    '#F97316', // Orange
    '#6B7280', // Gray
];

const selectColor = (c) => {
    categoryForm.color = c;
};

// Computed stats
const expenseCategories = computed(() => {
    return props.categories.filter(c => c.type === 'expense');
});

const incomeCategories = computed(() => {
    return props.categories.filter(c => c.type === 'income');
});

const totalBudgetLimit = computed(() => {
    return expenseCategories.value.reduce((sum, cat) => sum + (cat.budget_limit || 0), 0);
});

const totalBudgetSpent = computed(() => {
    return expenseCategories.value.reduce((sum, cat) => {
        if (cat.budget_limit > 0) {
            return sum + (cat.spent || 0);
        }
        return sum;
    }, 0);
});

const totalIncomeTargetLimit = computed(() => {
    return incomeCategories.value.reduce((sum, cat) => sum + (cat.budget_limit || 0), 0);
});

const totalIncomeTargetEarned = computed(() => {
    return incomeCategories.value.reduce((sum, cat) => {
        if (cat.budget_limit > 0) {
            return sum + (cat.earned || 0);
        }
        return sum;
    }, 0);
});

// Recurring Reminders Actions
const processReminder = (id) => {
    if (confirm('Are you sure you want to process this recurring item now? This will record the transaction/loan in the database and advance the next due date.')) {
        router.post(route('recurring.process', id), {}, { preserveScroll: true });
    }
};

const skipReminder = (id) => {
    if (confirm('Are you sure you want to skip this occurrence? This will advance the next due date without logging any transaction/loan.')) {
        router.post(route('recurring.skip', id), {}, { preserveScroll: true });
    }
};

// Account Actions
const openAddAccount = () => {
    isEditingAccount.value = false;
    accountForm.reset();
    accountForm.clearErrors();
    showAccountModal.value = true;
};

const openEditAccount = (acc) => {
    isEditingAccount.value = true;
    accountForm.id = acc.id;
    accountForm.name = acc.name;
    accountForm.type = acc.type;
    accountForm.initial_balance = acc.initial_balance;
    accountForm.color = acc.color || '#6366f1';
    accountForm.clearErrors();
    showAccountModal.value = true;
};

const submitAccount = () => {
    if (isEditingAccount.value) {
        accountForm.patch(route('accounts.update', accountForm.id), {
            onSuccess: () => {
                showAccountModal.value = false;
                accountForm.reset();
            },
        });
    } else {
        accountForm.post(route('accounts.store'), {
            onSuccess: () => {
                showAccountModal.value = false;
                accountForm.reset();
            },
        });
    }
};

const deleteAccount = (id) => {
    if (confirm('Are you sure you want to delete this account? All transactions linked to it will be un-allocated.')) {
        router.delete(route('accounts.destroy', id));
    }
};

const selectAccountColor = (c) => {
    accountForm.color = c;
};

// Filter states for Ledger
const selectedCategoryFilter = ref('');
const selectedTypeFilter = ref('');
const searchQuery = ref('');
const startDateFilter = ref('');
const endDateFilter = ref('');
const selectedAccountFilter = ref('');

// Pagination states
const currentPage = ref(1);
const perPage = ref(10);

// Reset page on filter changes
watch(
    [selectedCategoryFilter, selectedTypeFilter, searchQuery, startDateFilter, endDateFilter, selectedAccountFilter, perPage],
    () => {
        currentPage.value = 1;
    }
);

// Computed filtered transactions
const filteredTransactions = computed(() => {
    return props.recent_transactions.filter((tx) => {
        // Filter by Category
        const matchesCategory = !selectedCategoryFilter.value || 
            (selectedCategoryFilter.value === 'uncategorized' && !tx.category) ||
            (tx.category && tx.category.id === Number(selectedCategoryFilter.value));
        
        // Filter by Type
        const matchesType = !selectedTypeFilter.value || tx.type === selectedTypeFilter.value;
        
        // Filter by Account
        const matchesAccount = !selectedAccountFilter.value ||
            (selectedAccountFilter.value === 'unallocated' && !tx.account) ||
            (tx.account && tx.account.id === Number(selectedAccountFilter.value));
        
        // Filter by Search query
        const matchesSearch = !searchQuery.value || 
            (tx.description && tx.description.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            String(tx.amount).includes(searchQuery.value) ||
            (tx.category && tx.category.name.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (tx.account && tx.account.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
            
        // Filter by Date Range
        const matchesStartDate = !startDateFilter.value || tx.transaction_date >= startDateFilter.value;
        const matchesEndDate = !endDateFilter.value || tx.transaction_date <= endDateFilter.value;
            
        return matchesCategory && matchesType && matchesSearch && matchesStartDate && matchesEndDate && matchesAccount;
    });
});

// Computed pagination slice
const paginatedTransactions = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredTransactions.value.slice(start, end);
});

// Computed total pages count
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredTransactions.value.length / perPage.value));
});

// Computed totals for filtered ledger
const filteredLedgerStats = computed(() => {
    let income = 0.0;
    let expense = 0.0;
    filteredTransactions.value.forEach((tx) => {
        if (tx.type === 'income') {
            income += Number(tx.amount);
        } else {
            expense += Number(tx.amount);
        }
    });
    return {
        income,
        expense,
        net: income - expense,
        count: filteredTransactions.value.length
    };
});

// Click to filter by category and scroll down
const filterByCategory = (categoryId) => {
    selectedCategoryFilter.value = categoryId;
    document.getElementById('transaction-ledger-section')?.scrollIntoView({ behavior: 'smooth' });
};

// Click to filter by account and scroll down
const filterByAccount = (accountId) => {
    selectedAccountFilter.value = accountId;
    document.getElementById('transaction-ledger-section')?.scrollIntoView({ behavior: 'smooth' });
};

// Reset filters
const resetLedgerFilters = () => {
    selectedCategoryFilter.value = '';
    selectedTypeFilter.value = '';
    searchQuery.value = '';
    startDateFilter.value = '';
    endDateFilter.value = '';
    selectedAccountFilter.value = '';
};
</script>

<template>
    <Head title="Personal Budget Insights" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Personal Budget Insights
                </h2>
                
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Add Transaction Quick Button -->
                    <button 
                        @click="openAddTransaction"
                        class="px-4 py-2 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Transaction
                    </button>

                    <!-- Month Selector -->
                    <div class="flex items-center gap-2 bg-white dark:bg-slate-900 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <button 
                            @click="prevMonth"
                            class="p-2 text-slate-600 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors duration-150"
                            title="Previous Month"
                        >
                            <svg xmlns="http://www.w3.org/2050/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <input 
                            type="month" 
                            v-model="selectedMonth" 
                            @change="handleMonthChange"
                            class="bg-transparent text-slate-800 dark:text-slate-100 font-semibold border-none focus:ring-0 p-0 text-center text-sm w-32 cursor-pointer"
                        />

                        <button 
                            @click="nextMonth"
                            class="p-2 text-slate-600 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors duration-150"
                            title="Next Month"
                        >
                            <svg xmlns="http://www.w3.org/2050/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- Flash Notification Banner -->
                <div v-if="$page.props.flash.error" class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 p-4 rounded-2xl flex items-start gap-3 shadow-sm">
                    <div class="text-red-650 dark:text-red-400 mt-0.5 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-red-800 dark:text-red-400">Access Restricted</h4>
                        <p class="text-xs text-red-700 dark:text-red-300/85 mt-0.5 leading-relaxed">{{ $page.props.flash.error }}</p>
                    </div>
                </div>

                <div v-if="$page.props.flash.success" class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/30 p-4 rounded-2xl flex items-start gap-3 shadow-sm">
                    <div class="text-emerald-550 dark:text-emerald-400 mt-0.5 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-emerald-800 dark:text-emerald-400">Success</h4>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300/85 mt-0.5 leading-relaxed">{{ $page.props.flash.success }}</p>
                    </div>
                </div>

                <!-- If Ledger permission is disabled -->
                <div v-if="!$page.props.auth.user.is_superadmin && $page.props.auth.user.module_permissions && $page.props.auth.user.module_permissions.ledger === false" class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-200 dark:border-slate-800 p-8 text-center space-y-4">
                    <div class="w-16 h-16 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-450 flex items-center justify-center mx-auto shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-950 dark:text-white">Ledger Module Access Disabled</h3>
                    <p class="text-sm text-slate-500 max-w-lg mx-auto leading-relaxed">
                        Your account's access to the central financial ledger, account balances, and transaction logs has been disabled by the Administrator. Please use the navigation links above to access other enabled modules (like SaaS Subscriptions or Loans).
                    </p>
                </div>

                <div v-else class="space-y-8">

                    <!-- Today's Daily Summary Diagnostic Panel -->
                    <div v-if="daily_summary" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm animate-fade-in">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6 gap-4">
                            <div>
                                <h3 class="text-sm font-black text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-3.5 h-3.5 rounded-lg bg-indigo-500 flex-shrink-0 animate-pulse"></span>
                                    Today's Daily Summary
                                </h3>
                                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">
                                    Realtime tracking of today's expense budget allocation vs actual spends.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-slate-50/50 dark:bg-slate-950/40 rounded-2xl p-4 border border-slate-100/50 dark:border-slate-850/30">
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">Daily Budget Share</span>
                                <span class="text-xl font-black text-slate-850 dark:text-white mt-1 block">
                                    {{ formatCurrency(daily_summary.budget) }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-semibold mt-1 block">
                                    Capped average daily share: {{ formatCurrency(daily_summary.average_budget) }}
                                </span>
                            </div>

                            <div class="bg-slate-50/50 dark:bg-slate-950/40 rounded-2xl p-4 border border-slate-100/50 dark:border-slate-850/30">
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">Actual Expense Spent</span>
                                <span class="text-xl font-black text-rose-600 dark:text-rose-455 mt-1 block">
                                    {{ formatCurrency(daily_summary.actual) }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-semibold mt-1 block">
                                    Expenses logged today
                                </span>
                            </div>

                            <div class="bg-slate-50/50 dark:bg-slate-950/40 rounded-2xl p-4 border border-slate-100/50 dark:border-slate-850/30">
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">Cumulative Savings</span>
                                <span 
                                    class="text-xl font-black mt-1 block"
                                    :class="daily_summary.cumulative_savings >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-455'"
                                >
                                    {{ formatCurrency(daily_summary.cumulative_savings) }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-semibold mt-1 block">
                                    Carryover surplus this month
                                </span>
                            </div>
                        </div>
                    </div>
                
                <!-- Recurring Reminders Widget -->
                <div v-if="reminders && reminders.length > 0" class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-amber-100 dark:border-amber-900/30 p-6 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-black text-slate-950 dark:text-white flex items-center gap-2">
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                            </span>
                            Upcoming & Overdue Reminders
                        </h3>
                        <span class="text-xs text-amber-800 dark:text-amber-400 font-extrabold bg-amber-50 dark:bg-amber-950/20 px-2.5 py-1 rounded-lg">
                            {{ reminders.length }} Action Required
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div 
                            v-for="reminder in reminders" 
                            :key="reminder.id"
                            class="flex items-center justify-between p-4 rounded-xl border border-slate-150 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 hover:shadow-sm transition gap-4"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div 
                                    class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 shadow-sm"
                                    :class="[
                                        reminder.type === 'expense' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : '',
                                        reminder.type === 'loan_installment' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : '',
                                        reminder.type === 'loan' ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : '',
                                    ]"
                                >
                                    <svg v-if="reminder.type === 'expense'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                    <svg v-else-if="reminder.type === 'loan_installment'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <svg v-else-if="reminder.type === 'loan'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-extrabold text-slate-900 dark:text-white text-sm">
                                            {{ formatCurrency(reminder.amount) }}
                                        </span>
                                        <span 
                                            class="text-[9px] font-extrabold uppercase tracking-wider px-1.5 py-0.5 rounded-full"
                                            :class="reminder.is_overdue ? 'bg-red-100 dark:bg-red-950/40 text-red-650 dark:text-red-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-100'"
                                        >
                                            {{ reminder.is_overdue ? 'Overdue' : 'Due ' + reminder.next_due_date }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-700 dark:text-slate-100 truncate">
                                        <span v-if="reminder.type === 'expense'">
                                            Expense &bull; {{ reminder.category?.name || 'Uncategorized' }}
                                        </span>
                                        <span v-else-if="reminder.type === 'loan_installment'">
                                            Repayment: {{ reminder.loan?.person_name }}
                                        </span>
                                        <span v-else-if="reminder.type === 'loan'">
                                            {{ reminder.loan_type === 'lent' ? 'Lend to' : 'Borrow from' }}: {{ reminder.person_name }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button 
                                    @click="processReminder(reminder.id)"
                                    class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                                >
                                    Pay
                                </button>
                                <button 
                                    @click="skipReminder(reminder.id)"
                                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-650 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-lg shadow-sm transition"
                                >
                                    Skip
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Net Balance Card -->
                    <div class="relative overflow-hidden bg-gradient-to-br from-indigo-500 to-indigo-700 dark:from-indigo-650 dark:to-indigo-850 p-6 rounded-2xl shadow-xl text-white">
                        <div class="absolute -right-10 -bottom-10 opacity-10 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-44 w-44" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                        <div class="flex flex-col justify-between h-full">
                            <div>
                                <p class="text-indigo-100 text-xs font-semibold uppercase tracking-wider">Net Balance (All-Time)</p>
                                <h3 class="text-4xl font-extrabold mt-1 tracking-tight">
                                    {{ formatCurrency(stats.net_balance) }}
                                </h3>
                            </div>
                            <div class="mt-4 flex items-center text-xs">
                                <span class="bg-white/20 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    Overall ledger standing
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Income Card -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 opacity-5 text-emerald-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Monthly Income</p>
                            <h3 class="text-3xl font-bold mt-1 text-emerald-600 dark:text-emerald-400 tracking-tight">
                                {{ formatCurrency(stats.monthly_income) }}
                            </h3>
                        </div>
                        <div class="mt-4 text-xs text-slate-700 dark:text-slate-600 flex items-center gap-1">
                            <span class="text-emerald-500 font-semibold">Total Inflows</span> this month
                        </div>
                    </div>

                    <!-- Monthly Expenses Card -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 opacity-5 text-rose-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Monthly Expenses</p>
                            <h3 class="text-3xl font-bold mt-1 text-rose-600 dark:text-rose-400 tracking-tight">
                                {{ formatCurrency(stats.monthly_expenses) }}
                            </h3>
                        </div>
                        <div class="mt-4 text-xs text-slate-700 dark:text-slate-600 flex items-center gap-1">
                            <span class="text-rose-500 font-semibold">Total Outflows</span> this month
                        </div>
                    </div>
                </div>

                <!-- Main Layout Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Budgets & Categories Column -->
                    <div class="lg:col-span-2 space-y-8">
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                    Smart Budget Targets
                                </h3>
                                <div class="flex gap-2">
                                    <button 
                                        @click="openCategoryModal"
                                        class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-800 dark:text-slate-200 font-semibold text-xs rounded-xl transition duration-150 flex items-center gap-1.5"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Category
                                    </button>
                                </div>
                            </div>

                            <!-- Tab Switcher -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800 mb-6 gap-4">
                                <div class="flex gap-2">
                                    <button 
                                        type="button"
                                        @click="activeBudgetTab = 'expense'"
                                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all duration-150 -mb-[1px]"
                                        :class="activeBudgetTab === 'expense' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                    >
                                        Expense Budgets
                                    </button>
                                    <button 
                                        type="button"
                                        @click="activeBudgetTab = 'income'"
                                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all duration-150 -mb-[1px]"
                                        :class="activeBudgetTab === 'income' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                    >
                                        Income Targets
                                    </button>
                                </div>
                                <div class="pb-2 flex items-center gap-1.5 text-xs font-bold">
                                    <span class="text-slate-600 dark:text-slate-400 uppercase tracking-wider text-[10px]">Savings Target (Current Month):</span>
                                    <span class="text-sm font-black" :class="(totalIncomeTargetLimit - totalBudgetLimit) >= 0 ? 'text-emerald-650 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-455'">
                                        {{ (totalIncomeTargetLimit - totalBudgetLimit) >= 0 ? '+' : '' }}{{ formatCurrency(totalIncomeTargetLimit - totalBudgetLimit) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Expense Budgets Tab Content -->
                            <div v-if="activeBudgetTab === 'expense'" class="space-y-6">
                                <!-- Total Budget Summary Card -->
                                <div v-if="totalBudgetLimit > 0" class="mb-6 p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100/50 dark:border-indigo-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <p class="text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Monthly Budget Limit</p>
                                        <h4 class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                            {{ formatCurrency(totalBudgetLimit) }}
                                        </h4>
                                    </div>
                                    <div class="sm:text-right space-y-1">
                                        <p class="text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Spent on Budgeted Categories</p>
                                        <h4 class="text-xl font-extrabold" :class="totalBudgetSpent > totalBudgetLimit ? 'text-rose-600 dark:text-rose-450 animate-pulse' : 'text-slate-805 dark:text-slate-200'">
                                            {{ formatCurrency(totalBudgetSpent) }}
                                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                                 ({{ totalBudgetLimit > 0 ? ((totalBudgetSpent / totalBudgetLimit) * 100).toFixed(0) : 0 }}% Used)
                                            </span>
                                        </h4>
                                    </div>
                                </div>

                                <div v-if="expenseCategories.length === 0" class="py-12 text-center">
                                    <div class="bg-indigo-50 dark:bg-slate-950 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </div>
                                    <p class="text-slate-700 dark:text-slate-600 font-medium">No expense categories registered yet.</p>
                                    <p class="text-slate-600 dark:text-slate-700 text-xs mt-1">Create an expense category and set budget limits to track them.</p>
                                </div>

                                <div v-else class="space-y-6">
                                    <div 
                                        v-for="cat in expenseCategories" 
                                        :key="cat.id"
                                        class="p-4 rounded-xl border border-slate-100 dark:border-slate-800/60 bg-slate-50/50 dark:bg-slate-900/40 hover:shadow-sm transition-shadow duration-150"
                                    >
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                            <div class="flex items-center gap-2.5">
                                                <span 
                                                    class="w-3.5 h-3.5 rounded-full inline-block flex-shrink-0"
                                                    :style="{ backgroundColor: cat.color }"
                                                ></span>
                                                <h4 class="font-bold text-slate-800 dark:text-slate-200">{{ cat.name }}</h4>
                                                
                                                <!-- Occurrence Badge -->
                                                <span 
                                                    class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider"
                                                    :class="[
                                                        cat.expense_occurrence === 'daily' ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-650 dark:text-indigo-400' : '',
                                                        cat.expense_occurrence === 'weekly_one_time' ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-650 dark:text-purple-400' : '',
                                                        cat.expense_occurrence === 'one_time' ? 'bg-amber-50 dark:bg-amber-955/20 text-amber-600 dark:text-amber-400' : '',
                                                    ]"
                                                >
                                                    {{ cat.expense_occurrence === 'daily' ? 'Daily' : cat.expense_occurrence === 'weekly_one_time' ? 'Weekly' : 'One-time' }}
                                                </span>
                                                
                                                <!-- Filter Ledger Button -->
                                                <button 
                                                    @click="filterByCategory(cat.id)"
                                                    class="px-2 py-0.5 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 text-slate-450 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md transition-colors text-[10px] font-bold border border-slate-200/40 dark:border-slate-800/60"
                                                    title="Filter ledger by this category"
                                                >
                                                    Filter Ledger
                                                </button>

                                                <!-- Exceeded Alert -->
                                                <span 
                                                    v-if="cat.budget_limit > 0 && cat.spent > cat.budget_limit"
                                                    class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/45 text-rose-600 dark:text-rose-400 text-[10px] font-bold rounded-full border border-rose-100 dark:border-rose-900/50 flex items-center gap-1 animate-pulse"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Limit Exceeded
                                                </span>
                                            </div>

                                            <!-- Budget Numbers & Actions -->
                                            <div class="flex items-center gap-3">
                                                <div class="text-right text-xs">
                                                    <span class="font-bold text-slate-700 dark:text-slate-100">{{ formatCurrency(cat.spent) }}</span>
                                                    <span class="text-slate-600 font-medium"> of </span>
                                                    <span 
                                                        @click="openBudgetModal(cat)"
                                                        class="font-bold text-slate-700 dark:text-slate-600 hover:text-indigo-500 cursor-pointer underline decoration-dotted"
                                                        title="Click to change budget"
                                                        :id="'budget-limit-' + cat.id"
                                                    >
                                                        {{ cat.budget_limit > 0 ? formatCurrency(cat.budget_limit) : 'Set Limit' }}
                                                    </span>
                                                </div>
                                                <button 
                                                    @click="openBudgetModal(cat)"
                                                    class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-700 dark:hover:text-slate-350 rounded-lg transition-colors"
                                                    title="Edit Limit"
                                                    :id="'edit-budget-btn-' + cat.id"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Progress Bar Wrapper -->
                                        <div v-if="cat.budget_limit > 0" class="w-full space-y-1">
                                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3.5 overflow-hidden p-0.5 border border-slate-100 dark:border-slate-800">
                                                <div 
                                                    class="h-full rounded-full transition-all duration-500 shadow-inner"
                                                    :class="{
                                                        'bg-gradient-to-r from-emerald-400 to-emerald-500': cat.percentage_used < 70,
                                                        'bg-gradient-to-r from-amber-400 to-amber-500': cat.percentage_used >= 70 && cat.percentage_used <= 90,
                                                        'bg-gradient-to-r from-rose-500 to-rose-600': cat.percentage_used > 90
                                                    }"
                                                    :style="{ width: `${Math.min(cat.percentage_used, 100)}%` }"
                                                    :id="'progress-bar-' + cat.id"
                                                ></div>
                                            </div>
                                            <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider text-slate-600 px-1">
                                                <span>{{ cat.percentage_used }}% Used</span>
                                                <span>{{ formatCurrency(Math.max(0, cat.budget_limit - cat.spent)) }} Remaining</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-slate-600 dark:text-slate-700 italic mt-1 font-medium">
                                            No budget limit configured. Spent: {{ formatCurrency(cat.spent) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Income Targets Tab Content -->
                            <div v-if="activeBudgetTab === 'income'" class="space-y-6">
                                <!-- Total Income Target Summary Card -->
                                <div v-if="totalIncomeTargetLimit > 0" class="mb-6 p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100/50 dark:border-emerald-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <p class="text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Monthly Income Target</p>
                                        <h4 class="text-xl font-extrabold text-emerald-650 dark:text-emerald-400">
                                            {{ formatCurrency(totalIncomeTargetLimit) }}
                                        </h4>
                                    </div>
                                    <div class="sm:text-right space-y-1">
                                        <p class="text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Income Earned (Budgeted)</p>
                                        <h4 class="text-xl font-extrabold" :class="totalIncomeTargetEarned >= totalIncomeTargetLimit ? 'text-emerald-650 dark:text-emerald-400' : 'text-amber-650 dark:text-amber-500'">
                                            {{ formatCurrency(totalIncomeTargetEarned) }}
                                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                                 ({{ totalIncomeTargetLimit > 0 ? ((totalIncomeTargetEarned / totalIncomeTargetLimit) * 100).toFixed(0) : 0 }}% Earned)
                                            </span>
                                        </h4>
                                    </div>
                                </div>

                                <div v-if="incomeCategories.length === 0" class="py-12 text-center">
                                    <div class="bg-indigo-50 dark:bg-slate-950 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                    <p class="text-slate-700 dark:text-slate-600 font-medium">No income categories registered yet.</p>
                                    <p class="text-slate-600 dark:text-slate-700 text-xs mt-1">Create an income category and set monthly targets to track them.</p>
                                </div>

                                <div v-else class="space-y-6">
                                    <div 
                                        v-for="cat in incomeCategories" 
                                        :key="cat.id"
                                        class="p-4 rounded-xl border border-slate-100 dark:border-slate-800/60 bg-slate-50/50 dark:bg-slate-900/40 hover:shadow-sm transition-shadow duration-150"
                                    >
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                            <div class="flex items-center gap-2.5">
                                                <span 
                                                    class="w-3.5 h-3.5 rounded-full inline-block flex-shrink-0"
                                                    :style="{ backgroundColor: cat.color }"
                                                ></span>
                                                <h4 class="font-bold text-slate-800 dark:text-slate-200">{{ cat.name }}</h4>
                                                
                                                <!-- Target Reached Alert -->
                                                <span 
                                                    v-if="cat.budget_limit > 0 && cat.earned >= cat.budget_limit"
                                                    class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-950/45 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold rounded-full border border-emerald-100 dark:border-emerald-900/50 flex items-center gap-1"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l5-5z" clip-rule="evenodd" />
                                                    </svg>
                                                    Target Reached
                                                </span>
                                            </div>

                                            <!-- Budget Numbers & Actions -->
                                            <div class="flex items-center gap-3">
                                                <div class="text-right text-xs">
                                                    <span class="font-bold text-slate-700 dark:text-slate-100">{{ formatCurrency(cat.earned) }}</span>
                                                    <span class="text-slate-600 font-medium"> of </span>
                                                    <span 
                                                        @click="openBudgetModal(cat)"
                                                        class="font-bold text-slate-700 dark:text-slate-600 hover:text-indigo-500 cursor-pointer underline decoration-dotted"
                                                        title="Click to change target"
                                                        :id="'budget-limit-' + cat.id"
                                                    >
                                                        {{ cat.budget_limit > 0 ? formatCurrency(cat.budget_limit) : 'Set Target' }}
                                                    </span>
                                                </div>
                                                <button 
                                                    @click="openBudgetModal(cat)"
                                                    class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-700 dark:hover:text-slate-350 rounded-lg transition-colors"
                                                    title="Edit Target"
                                                    :id="'edit-budget-btn-' + cat.id"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Progress Bar Wrapper -->
                                        <div v-if="cat.budget_limit > 0" class="w-full space-y-1">
                                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3.5 overflow-hidden p-0.5 border border-slate-100 dark:border-slate-800">
                                                <div 
                                                    class="h-full rounded-full transition-all duration-500 shadow-inner"
                                                    :class="{
                                                        'bg-gradient-to-r from-amber-400 to-amber-500': cat.percentage_used < 50,
                                                        'bg-gradient-to-r from-indigo-400 to-indigo-500': cat.percentage_used >= 50 && cat.percentage_used < 100,
                                                        'bg-gradient-to-r from-emerald-500 to-emerald-600': cat.percentage_used >= 100
                                                    }"
                                                    :style="{ width: `${Math.min(cat.percentage_used, 100)}%` }"
                                                    :id="'progress-bar-' + cat.id"
                                                ></div>
                                            </div>
                                            <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider text-slate-600 px-1">
                                                <span>{{ cat.percentage_used }}% Earned</span>
                                                <span v-if="cat.earned < cat.budget_limit" class="text-rose-500 font-extrabold">
                                                    {{ formatCurrency(cat.deficit) }} Deficit
                                                </span>
                                                <span v-else class="text-emerald-500 font-extrabold">
                                                    {{ formatCurrency(cat.earned - cat.budget_limit) }} Surplus
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-slate-600 dark:text-slate-700 italic mt-1 font-medium">
                                            No income target configured. Earned: {{ formatCurrency(cat.earned) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Transactions Ledger Section -->
                        <div id="transaction-ledger-section" class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Monthly Transaction Ledger
                                </h3>
                                <button 
                                    @click="openAddTransaction"
                                    class="px-4 py-2 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5 self-start sm:self-auto"
                                    id="add-transaction-btn"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Transaction
                                </button>
                            </div>

                            <!-- Interactive Ledger Filters -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
                                <!-- Search Input -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">Search</label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            v-model="searchQuery"
                                            placeholder="Search description..."
                                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-805 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-600 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Category Filter -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">Category wise Ledger</label>
                                    <select 
                                        v-model="selectedCategoryFilter"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-805 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        <option value="">All Categories</option>
                                        <option value="uncategorized">Uncategorized</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                            {{ cat.name }} ({{ cat.type }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Account Filter -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">Account wise Ledger</label>
                                    <select 
                                        v-model="selectedAccountFilter"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-805 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        <option value="">All Accounts</option>
                                        <option value="unallocated">Unallocated / No Account</option>
                                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                            {{ acc.name }} ({{ acc.type.replace('_', ' ') }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Transaction Type Filter -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">Type</label>
                                    <select 
                                        v-model="selectedTypeFilter"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-850 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        <option value="">All Types</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>

                                <!-- Start Date Filter -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">Start Date</label>
                                    <input 
                                        type="date" 
                                        v-model="startDateFilter"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-805 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>

                                <!-- End Date Filter -->
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-405 dark:text-slate-600 mb-1.5">End Date</label>
                                    <input 
                                        type="date" 
                                        v-model="endDateFilter"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-805 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>

                            <!-- Reset/Clear Button Row -->
                            <div v-if="selectedCategoryFilter || selectedTypeFilter || searchQuery || startDateFilter || endDateFilter || selectedAccountFilter" class="flex justify-end mb-6 pb-4 border-b border-slate-100 dark:border-slate-800/80">
                                <button 
                                    @click="resetLedgerFilters"
                                    class="px-4 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-600 transition flex items-center gap-1"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Clear All Filters
                                </button>
                            </div>
                            <div v-else class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-800/80"></div>

                            <!-- Filtered Ledger Totals Summary -->
                            <div v-if="recent_transactions.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 mb-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-150 dark:border-slate-800">
                                <div class="space-y-0.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-405">Filtered Count</span>
                                    <p class="text-sm font-black text-slate-800 dark:text-slate-200">{{ filteredLedgerStats.count }} transactions</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-405">Total Income</span>
                                    <p class="text-sm font-black text-emerald-600 dark:text-emerald-450">{{ formatCurrency(filteredLedgerStats.income) }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-405">Total Expenses</span>
                                    <p class="text-sm font-black text-rose-600 dark:text-rose-450">{{ formatCurrency(filteredLedgerStats.expense) }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-405">Net Ledger Balance</span>
                                    <p 
                                        class="text-sm font-black"
                                        :class="filteredLedgerStats.net >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-455'"
                                    >
                                        {{ filteredLedgerStats.net >= 0 ? '+' : '' }}{{ formatCurrency(filteredLedgerStats.net) }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="recent_transactions.length === 0" class="py-12 text-center">
                                <div class="bg-indigo-50 dark:bg-slate-950 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-slate-700 dark:text-slate-600 font-medium">No transactions recorded yet.</p>
                                <p class="text-slate-600 dark:text-slate-700 text-xs mt-1">Press "Add Transaction" to log your first income or expense.</p>
                            </div>

                            <div v-else-if="filteredTransactions.length === 0" class="py-12 text-center">
                                <div class="bg-indigo-50/50 dark:bg-slate-950 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <p class="text-slate-700 dark:text-slate-600 font-bold text-sm">No transactions match your filters.</p>
                                <button 
                                    @click="resetLedgerFilters" 
                                    class="mt-2 text-xs font-bold text-indigo-650 dark:text-indigo-400 hover:underline"
                                >
                                    Clear Filters
                                </button>
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-600">
                                            <th class="py-3 px-4">Date</th>
                                            <th class="py-3 px-4">Description</th>
                                            <th class="py-3 px-4">Category</th>
                                            <th class="py-3 px-4">Account</th>
                                            <th class="py-3 px-4 text-right">Amount</th>
                                            <th class="py-3 px-4 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                        <tr 
                                            v-for="tx in paginatedTransactions" 
                                            :key="tx.id"
                                            class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                        >
                                            <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-600 text-xs whitespace-nowrap">
                                                {{ tx.transaction_date }}
                                            </td>
                                            <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100">
                                                {{ tx.description || 'Unspecified Description' }}
                                            </td>
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <span 
                                                    v-if="tx.category"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                    :style="{ 
                                                        backgroundColor: `${tx.category.color}15`, 
                                                        color: tx.category.color,
                                                        border: `1px solid ${tx.category.color}35`
                                                    }"
                                                >
                                                    <span 
                                                        class="w-1.5 h-1.5 rounded-full" 
                                                        :style="{ backgroundColor: tx.category.color }"
                                                    ></span>
                                                    {{ tx.category.name }}
                                                </span>
                                                <span v-else class="text-slate-600 text-xs italic font-medium">Uncategorized</span>
                                            </td>
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <span 
                                                    v-if="tx.account"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                    :style="{ 
                                                        backgroundColor: `${tx.account.color}15`, 
                                                        color: tx.account.color,
                                                        border: `1px solid ${tx.account.color}35`
                                                    }"
                                                >
                                                    <span 
                                                        class="w-1.5 h-1.5 rounded-full" 
                                                        :style="{ backgroundColor: tx.account.color }"
                                                    ></span>
                                                    {{ tx.account.name }}
                                                </span>
                                                <span v-else class="text-slate-600 text-xs italic font-medium">Unallocated</span>
                                            </td>
                                            <td 
                                                class="py-3.5 px-4 text-right font-extrabold whitespace-nowrap"
                                                :class="tx.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                            >
                                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                            </td>
                                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button 
                                                        @click="openEditTransaction(tx)"
                                                        class="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg transition-colors"
                                                        title="Edit"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button 
                                                        @click="deleteTransaction(tx.id)"
                                                        class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-600 hover:text-rose-600 dark:hover:text-rose-450 rounded-lg transition-colors"
                                                        title="Delete"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Controls -->
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6 pt-4 border-t border-slate-100 dark:border-slate-800/80 text-xs text-slate-700">
                                <div class="flex items-center gap-4">
                                    <span>
                                        Showing 
                                        <span class="font-bold text-slate-800 dark:text-slate-200">
                                            {{ filteredTransactions.length === 0 ? 0 : Math.min((currentPage - 1) * perPage + 1, filteredTransactions.length) }}
                                        </span>
                                        to 
                                        <span class="font-bold text-slate-800 dark:text-slate-200">
                                            {{ Math.min(currentPage * perPage, filteredTransactions.length) }}
                                        </span>
                                        of 
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ filteredTransactions.length }}</span> 
                                        transactions
                                    </span>
                                    
                                    <div class="flex items-center gap-1.5">
                                        <span>Per Page:</span>
                                        <select 
                                            v-model="perPage" 
                                            class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg py-1 px-2.5 text-[11px] font-bold text-slate-700 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                                        >
                                            <option :value="5">5</option>
                                            <option :value="10">10</option>
                                            <option :value="25">25</option>
                                            <option :value="50">50</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1" v-if="totalPages > 1">
                                    <!-- Prev Button -->
                                    <button 
                                        @click="currentPage--" 
                                        :disabled="currentPage === 1"
                                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 disabled:opacity-40 disabled:hover:bg-transparent font-semibold transition"
                                        title="Previous Page"
                                    >
                                        Prev
                                    </button>

                                    <!-- Page Number Buttons -->
                                    <button 
                                        v-for="page in totalPages" 
                                        :key="page"
                                        @click="currentPage = page"
                                        class="w-8 h-8 rounded-lg font-bold border transition"
                                        :class="currentPage === page 
                                            ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' 
                                            : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-100'"
                                    >
                                        {{ page }}
                                    </button>

                                    <!-- Next Button -->
                                    <button 
                                        @click="currentPage++" 
                                        :disabled="currentPage === totalPages"
                                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 disabled:opacity-40 disabled:hover:bg-transparent font-semibold transition"
                                        title="Next Page"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Categories sidebar panel -->
                    <div class="space-y-8">
                        <!-- Accounts Sidebar Card -->
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    My Accounts
                                </h3>
                                <button 
                                    @click="openAddAccount"
                                    class="px-2.5 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-800 dark:text-slate-200 font-semibold text-xs rounded-xl transition duration-150 flex items-center gap-1"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add
                                </button>
                            </div>

                            <div v-if="accounts.length === 0" class="py-6 text-center text-slate-600 dark:text-slate-700 italic text-xs font-semibold">
                                No accounts registered. Click "Add" above to create one.
                            </div>

                            <div v-else class="space-y-3">
                                <div 
                                    v-for="acc in accounts" 
                                    :key="acc.id"
                                    class="flex items-center justify-between p-3 rounded-xl border border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/20 hover:shadow-sm transition-shadow"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span 
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                                            :style="{ backgroundColor: acc.color }"
                                        >
                                            <svg v-if="acc.type === 'bank'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                            <svg v-else-if="acc.type === 'mobile_wallet'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                            <svg v-else-if="acc.type === 'cash'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                            <svg v-else-if="acc.type === 'credit_card'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p 
                                                @click="filterByAccount(acc.id)"
                                                class="font-bold text-sm text-slate-800 dark:text-slate-200 cursor-pointer hover:text-indigo-650 dark:hover:text-indigo-400 hover:underline truncate"
                                                title="Filter ledger by this account"
                                            >
                                                {{ acc.name }}
                                            </p>
                                            <p class="text-[10px] font-medium text-slate-450 dark:text-slate-600 uppercase tracking-wider">
                                                {{ acc.type.replace('_', ' ') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-right">
                                            <span 
                                                class="font-black text-sm block"
                                                :class="acc.current_balance >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-450'"
                                            >
                                                {{ formatCurrency(acc.current_balance) }}
                                            </span>
                                        </div>
                                        <div class="flex gap-0.5">
                                            <button 
                                                @click="openEditAccount(acc)"
                                                class="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-650 dark:hover:text-slate-350 rounded-lg transition-colors"
                                                title="Edit Account"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="deleteAccount(acc.id)"
                                                class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-600 hover:text-rose-650 dark:hover:text-rose-400 rounded-lg transition-colors"
                                                title="Delete Account"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories List Card -->
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                Categories List
                            </h3>

                            <div v-if="categories.length === 0" class="py-6 text-center text-slate-600 dark:text-slate-700 italic text-xs font-semibold">
                                No categories. Click "Add Category" above to create one.
                            </div>

                            <div v-else class="space-y-2">
                                <div 
                                    v-for="cat in categories" 
                                    :key="cat.id"
                                    class="flex items-center justify-between p-2.5 rounded-xl border border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/20"
                                >
                                    <div class="flex items-center gap-2">
                                        <span 
                                            class="w-3.5 h-3.5 rounded-full inline-block flex-shrink-0"
                                            :style="{ backgroundColor: cat.color }"
                                        ></span>
                                        <span 
                                            @click="filterByCategory(cat.id)"
                                            class="font-bold text-sm text-slate-805 dark:text-slate-200 cursor-pointer hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline"
                                            title="Click to view ledger for this category"
                                        >{{ cat.name }}</span>
                                        <span 
                                            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase border"
                                            :class="cat.type === 'income' 
                                                ? 'bg-emerald-50 border-emerald-100 text-emerald-600 dark:bg-emerald-950/20 dark:border-emerald-900/40 dark:text-emerald-400' 
                                                : 'bg-rose-50 border-rose-100 text-rose-600 dark:bg-rose-950/20 dark:border-rose-900/40 dark:text-rose-400'"
                                        >
                                            {{ cat.type }}
                                        </span>
                                        <span 
                                            v-if="cat.type === 'expense'"
                                            class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider"
                                            :class="[
                                                cat.expense_occurrence === 'daily' ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-650 dark:text-indigo-400' : '',
                                                cat.expense_occurrence === 'weekly_one_time' ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-650 dark:text-purple-400' : '',
                                                cat.expense_occurrence === 'one_time' ? 'bg-amber-50 dark:bg-amber-955/20 text-amber-600 dark:text-amber-400' : '',
                                            ]"
                                        >
                                            {{ cat.expense_occurrence === 'daily' ? 'Daily' : cat.expense_occurrence === 'weekly_one_time' ? 'Weekly' : 'One-time' }}
                                        </span>
                                    </div>
                                    <button 
                                        @click="deleteCategory(cat.id)"
                                        class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-600 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg transition-colors"
                                        title="Delete Category"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Android APK Download Banner -->
                <div class="bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-md relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full blur-2xl pointer-events-none"></div>
                    
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                📱 CashBox Android Mobile Companion App
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30 border border-emerald-100">
                                    APK Available
                                </span>
                            </h4>
                            <p class="text-xs text-slate-700 dark:text-slate-400 mt-1 leading-relaxed max-w-xl">
                                Track categories, monitor budgets, and view live ledger transactions directly from your Android phone. Fully compatible with Android 8.0+ (~6.1 MB).
                            </p>
                        </div>
                    </div>

                    <a
                        href="/Cash_Box_BD.apk"
                        download="Cash_Box_BD.apk"
                        class="shrink-0 w-full md:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-indigo-650 hover:bg-indigo-600 text-white font-extrabold text-xs shadow-md shadow-indigo-600/15 transition-all duration-150 active:scale-95 border border-indigo-500/20"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download APK (v1.0.0)
                    </a>
                </div>
            </div>
        </div>
        </div>

        <!-- TRANSACTION MODAL -->
        <div v-if="showTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-lg mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            {{ isEditingTransaction ? 'Modify Transaction' : 'Log New Transaction' }}
                        </h3>
                        <button 
                            @click="showTransactionModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            id="close-transaction-modal-btn"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitTransaction">
                        <div class="p-6 space-y-4">
                            <!-- Type -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Transaction Type</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <button 
                                        type="button"
                                        @click="transactionForm.type = 'expense'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition duration-150"
                                        :class="transactionForm.type === 'expense' 
                                            ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-rose-950/30 dark:border-rose-500 dark:text-rose-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="type-expense-btn"
                                    >
                                        Expense
                                    </button>
                                    <button 
                                        type="button"
                                        @click="transactionForm.type = 'income'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition duration-150"
                                        :class="transactionForm.type === 'income' 
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-emerald-950/30 dark:border-emerald-500 dark:text-emerald-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="type-income-btn"
                                    >
                                        Income
                                    </button>
                                    <button 
                                        type="button"
                                        @click="transactionForm.type = 'transfer'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition duration-150"
                                        :class="transactionForm.type === 'transfer' 
                                            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 dark:bg-indigo-950/30 dark:border-indigo-500 dark:text-indigo-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="type-transfer-btn"
                                    >
                                        Transfer
                                    </button>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label for="tx-amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Amount (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="tx-amount" 
                                    v-model="transactionForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="0.00"
                                    required
                                />
                                <div v-if="transactionForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.amount }}</div>
                            </div>

                            <!-- Category -->
                            <div v-if="transactionForm.type !== 'transfer'">
                                <label for="tx-category" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Category</label>
                                <select 
                                    id="tx-category" 
                                    v-model="transactionForm.category_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">Uncategorized</option>
                                    <option 
                                        v-for="cat in categories.filter(c => c.type === transactionForm.type)" 
                                        :key="cat.id" 
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <div v-if="transactionForm.errors.category_id" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.category_id }}</div>
                            </div>

                            <!-- Account (for standard transactions) -->
                            <div v-if="transactionForm.type !== 'transfer'">
                                <label for="tx-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Account</label>
                                <select 
                                    id="tx-account" 
                                    v-model="transactionForm.account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">No Account / Unallocated</option>
                                    <option 
                                        v-for="acc in accounts" 
                                        :key="acc.id" 
                                        :value="acc.id"
                                    >
                                        {{ acc.name }} ({{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <div v-if="transactionForm.errors.account_id" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.account_id }}</div>
                            </div>

                            <!-- From Account (for transfers) -->
                            <div v-if="transactionForm.type === 'transfer'">
                                <label for="tx-from-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">From Account</label>
                                <select 
                                    id="tx-from-account" 
                                    v-model="transactionForm.from_account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                >
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} ({{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <div v-if="transactionForm.errors.from_account_id" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.from_account_id }}</div>
                            </div>

                            <!-- To Account (for transfers) -->
                            <div v-if="transactionForm.type === 'transfer'">
                                <label for="tx-to-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">To Account</label>
                                <select 
                                    id="tx-to-account" 
                                    v-model="transactionForm.to_account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                >
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} ({{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <div v-if="transactionForm.errors.to_account_id" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.to_account_id }}</div>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label for="tx-date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Date</label>
                                <input 
                                    type="date" 
                                    id="tx-date" 
                                    v-model="transactionForm.transaction_date"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                />
                                <div v-if="transactionForm.errors.transaction_date" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.transaction_date }}</div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="tx-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Description</label>
                                <textarea 
                                    id="tx-desc" 
                                    v-model="transactionForm.description"
                                    rows="3"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Enter description..."
                                ></textarea>
                                <div v-if="transactionForm.errors.description" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.description }}</div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button 
                                type="button" 
                                @click="showTransactionModal = false"
                                class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition duration-150"
                                :disabled="transactionForm.processing"
                                id="save-transaction-btn"
                            >
                                {{ transactionForm.processing ? 'Saving...' : 'Save Transaction' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- CATEGORY MODAL -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            Create Category
                        </h3>
                        <button 
                            @click="showCategoryModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            id="close-category-modal-btn"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitCategory">
                        <div class="p-6 space-y-4">
                            <!-- Category Name -->
                            <div>
                                <label for="cat-name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Category Name</label>
                                <input 
                                    type="text" 
                                    id="cat-name" 
                                    v-model="categoryForm.name"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Food, Salary, Utilities..."
                                    required
                                />
                                <div v-if="categoryForm.errors.name" class="text-rose-500 text-xs mt-1">{{ categoryForm.errors.name }}</div>
                            </div>

                            <!-- Category Type -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Category Type</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button 
                                        type="button"
                                        @click="categoryForm.type = 'expense'"
                                        class="py-2 rounded-xl text-sm font-bold border transition duration-150"
                                        :class="categoryForm.type === 'expense' 
                                            ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-rose-950/30 dark:border-rose-500 dark:text-rose-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="cat-type-expense-btn"
                                    >
                                        Expense Category
                                    </button>
                                    <button 
                                        type="button"
                                        @click="categoryForm.type = 'income'"
                                        class="py-2 rounded-xl text-sm font-bold border transition duration-150"
                                        :class="categoryForm.type === 'income' 
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-emerald-950/30 dark:border-emerald-500 dark:text-emerald-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="cat-type-income-btn"
                                    >
                                        Income Category
                                    </button>
                                </div>
                                <div v-if="categoryForm.errors.type" class="text-rose-500 text-xs mt-1">{{ categoryForm.errors.type }}</div>
                            </div>

                            <!-- Color Palette -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Theme Color</label>
                                <div class="flex flex-wrap gap-2.5">
                                    <button 
                                        v-for="color in colors" 
                                        :key="color"
                                        type="button"
                                        @click="selectColor(color)"
                                        class="w-7 h-7 rounded-full flex-shrink-0 border-2 transition duration-150 focus:outline-none focus:scale-110"
                                        :style="{ backgroundColor: color }"
                                        :class="categoryForm.color === color ? 'border-slate-800 dark:border-white scale-110 ring-2 ring-indigo-500' : 'border-transparent hover:scale-105'"
                                    ></button>
                                </div>
                                <div v-if="categoryForm.errors.color" class="text-rose-500 text-xs mt-1">{{ categoryForm.errors.color }}</div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button 
                                type="button" 
                                @click="showCategoryModal = false"
                                class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition duration-150"
                                :disabled="categoryForm.processing"
                                id="save-category-btn"
                            >
                                {{ categoryForm.processing ? 'Creating...' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- BUDGET MODAL -->
        <div v-if="showBudgetModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-sm mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
                            Set Budget Limit
                        </h3>
                        <button 
                            @click="showBudgetModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            id="close-budget-modal-btn"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitBudget">
                        <div class="p-6 space-y-4">
                            <div class="text-xs font-bold text-slate-700 dark:text-slate-600 bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                                Category: <span class="text-slate-850 dark:text-slate-100 font-extrabold">{{ selectedCategoryForBudget?.name }}</span>
                                <template v-if="selectedCategoryForBudget?.type === 'income'">
                                    <br/>
                                    Target Month: <span class="text-slate-850 dark:text-slate-100 font-extrabold">{{ budgetForm.month }}</span>
                                </template>
                            </div>

                            <!-- Budget Limit Amount -->
                            <div>
                                <label for="budget-limit" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                    {{ selectedCategoryForBudget?.type === 'income' ? 'Monthly Income Target (৳)' : 'Monthly Budget Limit (৳)' }}
                                </label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="budget-limit" 
                                    v-model="budgetForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    :placeholder="selectedCategoryForBudget?.type === 'income' ? 'Enter target earned amount...' : 'Enter maximum spend...'"
                                    required
                                    min="0"
                                />
                                <div v-if="budgetForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ budgetForm.errors.amount }}</div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button 
                                type="button" 
                                @click="showBudgetModal = false"
                                class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition duration-150"
                                :disabled="budgetForm.processing"
                                id="save-budget-btn"
                            >
                                {{ budgetForm.processing ? 'Saving...' : 'Save Limit' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ACCOUNT MODAL -->
        <div v-if="showAccountModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            {{ isEditingAccount ? 'Modify Account' : 'Create New Account' }}
                        </h3>
                        <button 
                            @click="showAccountModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            id="close-account-modal-btn"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitAccount">
                        <div class="p-6 space-y-4">
                            <!-- Account Name -->
                            <div>
                                <label for="acc-name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Account Name</label>
                                <input 
                                    type="text" 
                                    id="acc-name" 
                                    v-model="accountForm.name"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="City Bank, bKash Personal, Pocket Cash..."
                                    required
                                />
                                <div v-if="accountForm.errors.name" class="text-rose-500 text-xs mt-1">{{ accountForm.errors.name }}</div>
                            </div>

                            <!-- Account Type -->
                            <div>
                                <label for="acc-type" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Account Type</label>
                                <select 
                                    id="acc-type" 
                                    v-model="accountForm.type"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="bank">Bank Account</option>
                                    <option value="mobile_wallet">Mobile Wallet (bKash, Nagad, etc.)</option>
                                    <option value="cash">Cash / Wallet</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="other">Other Account</option>
                                </select>
                                <div v-if="accountForm.errors.type" class="text-rose-500 text-xs mt-1">{{ accountForm.errors.type }}</div>
                            </div>

                            <!-- Initial Balance -->
                            <div>
                                <label for="acc-balance" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Initial Balance</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="acc-balance" 
                                    v-model="accountForm.initial_balance"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="0.00"
                                    required
                                />
                                <div v-if="accountForm.errors.initial_balance" class="text-rose-500 text-xs mt-1">{{ accountForm.errors.initial_balance }}</div>
                            </div>

                            <!-- Theme Color -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Theme Color</label>
                                <div class="flex flex-wrap gap-2.5">
                                    <button 
                                        v-for="color in colors" 
                                        :key="color"
                                        type="button"
                                        @click="selectAccountColor(color)"
                                        class="w-7 h-7 rounded-full flex-shrink-0 border-2 transition duration-150 focus:outline-none focus:scale-110"
                                        :style="{ backgroundColor: color }"
                                        :class="accountForm.color === color ? 'border-slate-800 dark:border-white scale-110 ring-2 ring-indigo-500' : 'border-transparent hover:scale-105'"
                                    ></button>
                                </div>
                                <div v-if="accountForm.errors.color" class="text-rose-500 text-xs mt-1">{{ accountForm.errors.color }}</div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button 
                                type="button" 
                                @click="showAccountModal = false"
                                class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition duration-150"
                                :disabled="accountForm.processing"
                                id="save-account-btn"
                            >
                                {{ accountForm.processing ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom style for input type="month" datepicker removal of borders inside standard input */
input[type="month"]::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
    height: auto;
}
</style>
