<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    transactions: Object,
    categories: Array,
    accounts: Array,
    filters: Object,
    stats: Object,
});

// Filters State
const searchQuery = ref(props.filters.search || '');
const filterType = ref(props.filters.type || '');
const filterCategory = ref(props.filters.category_id || '');
const filterAccount = ref(props.filters.account_id || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

// Modals State
const showTransactionModal = ref(false);
const isEditingTransaction = ref(false);

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

// Computations: Filtered categories for creation modal
const modalCategories = computed(() => {
    return props.categories.filter(c => c.type === transactionForm.type);
});

// Filter transactions locally for quick calculations of stats
const stats = computed(() => {
    return props.stats || { income: 0, expense: 0, net: 0 };
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

// Filter triggering
const applyFilters = () => {
    router.get(route('transactions.index'), {
        search: searchQuery.value,
        type: filterType.value,
        category_id: filterCategory.value,
        account_id: filterAccount.value,
        start_date: startDate.value,
        end_date: endDate.value
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    filterType.value = '';
    filterCategory.value = '';
    filterAccount.value = '';
    startDate.value = '';
    endDate.value = '';
    applyFilters();
};

// Watchers to auto-apply filters on select/date changes
watch([filterType, filterCategory, filterAccount, startDate, endDate], () => {
    applyFilters();
});

// Open Modals
const openAddTransaction = () => {
    isEditingTransaction.value = false;
    transactionForm.reset();
    transactionForm.clearErrors();
    transactionForm.transaction_date = new Date().toISOString().split('T')[0];
    
    // Set default category and account
    const expenseCats = props.categories.filter(c => c.type === 'expense');
    if (expenseCats.length > 0) {
        transactionForm.category_id = expenseCats[0].id;
    }
    if (props.accounts.length > 0) {
        transactionForm.account_id = props.accounts[0].id;
        transactionForm.from_account_id = props.accounts[0].id;
        if (props.accounts.length > 1) {
            transactionForm.to_account_id = props.accounts[1].id;
        } else {
            transactionForm.to_account_id = '';
        }
    }
    showTransactionModal.value = true;
};

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

// Handle modal type changes to prefill appropriate categories
watch(() => transactionForm.type, (newType) => {
    if (newType === 'transfer') {
        transactionForm.category_id = '';
    } else {
        const filtered = props.categories.filter(c => c.type === newType);
        if (filtered.length > 0) {
            transactionForm.category_id = filtered[0].id;
        } else {
            transactionForm.category_id = '';
        }
    }
});

const submitTransaction = () => {
    if (transactionForm.type === 'transfer') {
        if (isEditingTransaction.value) {
            transactionForm.patch(route('transfers.update', transactionForm.id), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                }
            });
        } else {
            transactionForm.post(route('transfers.store'), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                }
            });
        }
    } else {
        if (isEditingTransaction.value) {
            transactionForm.patch(route('transactions.update', transactionForm.id), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                }
            });
        } else {
            transactionForm.post(route('transactions.store'), {
                onSuccess: () => {
                    showTransactionModal.value = false;
                    transactionForm.reset();
                }
            });
        }
    }
};

const deleteTransaction = (id) => {
    if (confirm('Are you sure you want to delete this transaction?')) {
        router.delete(route('transactions.destroy', id));
    }
};
</script>

<template>
    <Head title="Ledger Transactions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        Transactions Ledger
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        View, search, filter, and modify all financial ledger entries in one single place.
                    </p>
                </div>
                <button 
                    @click="openAddTransaction"
                    class="px-4 py-2.5 bg-indigo-700 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Transaction
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
            <!-- Stats Strip -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Filtered Income</span>
                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-450 mt-1 block">{{ formatCurrency(stats.income) }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Filtered Expenses</span>
                        <span class="text-lg font-black text-rose-650 dark:text-rose-400 mt-1 block">{{ formatCurrency(stats.expense) }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Net Cash Flow</span>
                        <span 
                            class="text-lg font-black mt-1 block"
                            :class="stats.net >= 0 ? 'text-indigo-650 dark:text-indigo-400' : 'text-rose-600 dark:text-rose-450'"
                        >
                            {{ formatCurrency(stats.net) }}
                        </span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-850 dark:text-slate-205">Filter & Search</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Search Input -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">Search Description</label>
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            @keyup.enter="applyFilters"
                            placeholder="Type keyword..."
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        />
                    </div>
                    <!-- Type filter -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">Flow Type</label>
                        <select 
                            v-model="filterType"
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="">All Types</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <!-- Category filter -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">Category Target</label>
                        <select 
                            v-model="filterCategory"
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }} ({{ cat.type }})
                            </option>
                        </select>
                    </div>
                    <!-- Account filter -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">Financial Account</label>
                        <select 
                            v-model="filterAccount"
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="">All Accounts</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }}
                            </option>
                        </select>
                    </div>
                    <!-- Start Date Filter -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">Start Date</label>
                        <input 
                            type="date" 
                            v-model="startDate" 
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        />
                    </div>
                    <!-- End Date Filter -->
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-450 dark:text-slate-600 mb-1">End Date</label>
                        <input 
                            type="date" 
                            v-model="endDate" 
                            class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button 
                        @click="resetFilters"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-900 dark:text-white font-bold text-xs rounded-lg transition"
                    >
                        Reset
                    </button>
                    <button 
                        @click="applyFilters"
                        class="px-4 py-2 bg-indigo-650 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition shadow-sm"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                <div v-if="transactions.data.length === 0" class="py-16 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                    No transactions found matching the selected filters.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Account / Flow</th>
                                <th class="py-3 px-4 text-right">Amount</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <tr 
                                v-for="tx in transactions.data" 
                                :key="tx.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-905/30 transition duration-150"
                            >
                                <td class="py-3.5 px-4 font-bold text-slate-600 dark:text-slate-400">
                                    {{ tx.transaction_date }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-slate-200">
                                    {{ tx.description || 'No description notes' }}
                                    <span v-if="tx.is_transfer" class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-850 dark:text-slate-400">
                                        Transfer
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span 
                                        v-if="tx.category" 
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider"
                                        :style="{ backgroundColor: `${tx.category.color}15`, color: tx.category.color }"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: tx.category.color }"></span>
                                        {{ tx.category.name }}
                                    </span>
                                    <span v-else class="text-slate-400 italic">Uncategorized</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div v-if="tx.is_transfer && tx.account && tx.transfer_account" class="flex items-center gap-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                                        <span>{{ tx.type === 'expense' ? tx.account.name : tx.transfer_account.name }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                        <span>{{ tx.type === 'expense' ? tx.transfer_account.name : tx.account.name }}</span>
                                    </div>
                                    <div v-else-if="tx.account" class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: tx.account.color }"></span>
                                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ tx.account.name }}</span>
                                    </div>
                                    <span v-else class="text-slate-400 italic">None</span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-sm">
                                    <span 
                                        :class="[
                                            tx.is_transfer 
                                                ? 'text-slate-650 dark:text-slate-350' 
                                                : tx.type === 'income' 
                                                    ? 'text-emerald-600 dark:text-emerald-450' 
                                                    : 'text-rose-650 dark:text-rose-455'
                                        ]"
                                    >
                                        {{ tx.is_transfer ? '' : tx.type === 'income' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button 
                                            @click="openEditTransaction(tx)"
                                            class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-all flex items-center justify-center"
                                            title="Edit Transaction"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteTransaction(tx.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/20 text-slate-400 hover:text-red-650 dark:hover:text-red-400 transition-all flex items-center justify-center"
                                            title="Delete Transaction"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-100 dark:border-slate-800/80 pt-6 mt-6 gap-4">
                        <div class="text-xs text-slate-500 font-semibold">
                            Showing 
                            <span class="font-extrabold text-slate-850 dark:text-slate-200">{{ transactions.from || 0 }}</span> 
                            to 
                            <span class="font-extrabold text-slate-850 dark:text-slate-200">{{ transactions.to || 0 }}</span> 
                            of 
                            <span class="font-extrabold text-slate-850 dark:text-slate-200">{{ transactions.total || 0 }}</span> 
                            results
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-1">
                            <template v-for="(link, key) in transactions.links" :key="key">
                                <div 
                                    v-if="link.url === null"
                                    class="px-3.5 py-2 text-xs font-bold text-slate-400 bg-slate-50 dark:bg-slate-900 border border-slate-150 dark:border-slate-800 rounded-xl cursor-not-allowed opacity-60"
                                    v-html="link.label"
                                ></div>
                                
                                <Link
                                    v-else
                                    :href="link.url"
                                    class="px-3.5 py-2 text-xs font-bold rounded-xl border transition-all duration-150"
                                    :class="link.active 
                                        ? 'bg-indigo-600 border-indigo-600 text-white shadow-md shadow-indigo-500/10' 
                                        : 'bg-white hover:bg-slate-50 border-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-800 text-slate-650 dark:text-slate-300'"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Transaction Modal -->
        <div v-if="showTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
                <button 
                    @click="showTransactionModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-650 dark:hover:text-slate-205 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-base font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4">
                    {{ isEditingTransaction ? 'Modify Entry' : 'Create Transaction' }}
                </h3>

                <form @submit.prevent="submitTransaction" class="space-y-4">
                    <!-- Flow Type -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Transaction Type
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button 
                                type="button"
                                @click="transactionForm.type = 'expense'"
                                class="py-2 rounded-xl text-xs font-black uppercase tracking-wider border transition"
                                :class="transactionForm.type === 'expense' 
                                    ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-slate-950 dark:border-rose-500 dark:text-rose-400' 
                                    : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            >
                                Expense
                            </button>
                            <button 
                                type="button"
                                @click="transactionForm.type = 'income'"
                                class="py-2 rounded-xl text-xs font-black uppercase tracking-wider border transition"
                                :class="transactionForm.type === 'income' 
                                    ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-slate-950 dark:border-emerald-500 dark:text-emerald-400' 
                                    : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            >
                                Income
                            </button>
                            <button 
                                type="button"
                                @click="transactionForm.type = 'transfer'"
                                class="py-2 rounded-xl text-xs font-black uppercase tracking-wider border transition"
                                :class="transactionForm.type === 'transfer' 
                                    ? 'bg-indigo-50 border-indigo-500 text-indigo-700 dark:bg-slate-950 dark:border-indigo-500 dark:text-indigo-400' 
                                    : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            >
                                Transfer
                            </button>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Transaction Amount (৳)
                        </label>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0.01" 
                            v-model="transactionForm.amount" 
                            placeholder="0.00"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="transactionForm.errors.amount" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.amount }}</p>
                    </div>

                    <!-- Category (hidden for transfers) -->
                    <div v-if="transactionForm.type !== 'transfer'">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Category Target
                        </label>
                        <select 
                            v-model="transactionForm.category_id"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="">No Category</option>
                            <option v-for="cat in modalCategories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <p v-if="transactionForm.errors.category_id" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.category_id }}</p>
                    </div>

                    <!-- Account inputs (standard vs transfers) -->
                    <div v-if="transactionForm.type !== 'transfer'">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Financial Account
                        </label>
                        <select 
                            v-model="transactionForm.account_id"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                            required
                        >
                            <option value="" disabled>Choose account...</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }} (Bal: {{ formatCurrency(acc.current_balance) }})
                            </option>
                        </select>
                        <p v-if="transactionForm.errors.account_id" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.account_id }}</p>
                    </div>

                    <!-- For Fund Transfers -->
                    <div v-else class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                                From Account
                            </label>
                            <select 
                                v-model="transactionForm.from_account_id"
                                class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                                required
                            >
                                <option value="" disabled>Source account...</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id" :disabled="acc.id === transactionForm.to_account_id">
                                    {{ acc.name }}
                                </option>
                            </select>
                            <p v-if="transactionForm.errors.from_account_id" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.from_account_id }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                                To Account
                            </label>
                            <select 
                                v-model="transactionForm.to_account_id"
                                class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                                required
                            >
                                <option value="" disabled>Dest account...</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id" :disabled="acc.id === transactionForm.from_account_id">
                                    {{ acc.name }}
                                </option>
                            </select>
                            <p v-if="transactionForm.errors.to_account_id" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.to_account_id }}</p>
                        </div>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Transaction Date
                        </label>
                        <input 
                            type="date" 
                            v-model="transactionForm.transaction_date" 
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                            required
                        />
                        <p v-if="transactionForm.errors.transaction_date" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.transaction_date }}</p>
                    </div>

                    <!-- Description Notes -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Memo notes (Optional)
                        </label>
                        <textarea 
                            v-model="transactionForm.description" 
                            rows="2"
                            placeholder="Details or references..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        ></textarea>
                        <p v-if="transactionForm.errors.description" class="text-xs text-rose-500 mt-1 font-semibold">{{ transactionForm.errors.description }}</p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="transactionForm.processing"
                        class="w-full h-11 bg-indigo-700 hover:bg-indigo-650 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        {{ isEditingTransaction ? 'Save Changes' : 'Record Transaction' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
