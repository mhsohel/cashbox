<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    loans: {
        type: Array,
        required: true,
    },
    repayments: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    accounts: {
        type: Array,
        default: () => [],
    },
});

// Format Currency
const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

// Modals State
const showLoanModal = ref(false);
const showRepaymentModal = ref(false);
const selectedLoanForRepayment = ref(null);

// Filter Tab State: 'lent' or 'borrowed'
const activeTab = ref('lent');

// Active status filter: 'active' or 'all' or 'repaid'
const statusFilter = ref('active');

// Forms
const loanForm = useForm({
    person_name: '',
    type: 'lent',
    amount: '',
    due_date: '',
    description: '',
    account_id: '',
});

const repaymentForm = useForm({
    amount: '',
    account_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
});

// Submit Loan
const submitLoan = () => {
    loanForm.post(route('loans.store'), {
        onSuccess: () => {
            showLoanModal.value = false;
            loanForm.reset();
        },
    });
};

// Open Log Repayment Modal
const openRepaymentModal = (loan) => {
    selectedLoanForRepayment.value = loan;
    repaymentForm.amount = loan.outstanding_balance;
    repaymentForm.account_id = props.accounts.length > 0 ? props.accounts[0].id : '';
    repaymentForm.transaction_date = new Date().toISOString().split('T')[0];
    repaymentForm.description = `Repayment of ${loan.type === 'lent' ? 'lent' : 'borrowed'} money - ${loan.person_name}`;
    repaymentForm.clearErrors();
    showRepaymentModal.value = true;
};

// Submit Repayment
const submitRepayment = () => {
    repaymentForm.post(route('loans.repayment', selectedLoanForRepayment.value.id), {
        onSuccess: () => {
            showRepaymentModal.value = false;
            repaymentForm.reset();
        },
    });
};

// Delete Loan
const deleteLoan = (id) => {
    if (confirm('Are you sure you want to delete this loan? This will delete all associated transactions (initial transactions and repayments).')) {
        router.delete(route('loans.destroy', id));
    }
};

// Filtered Loans based on Active Tab and Status Filter
const filteredLoans = computed(() => {
    return props.loans.filter((loan) => {
        const matchesType = loan.type === activeTab.value;
        const matchesStatus = statusFilter.value === 'all' || loan.status === statusFilter.value;
        return matchesType && matchesStatus;
    });
});
</script>

<template>
    <Head title="Loans & Debts Manager" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Loans & Debts Manager
                </h2>
                <div class="flex items-center gap-3">
                    <button 
                        @click="showLoanModal = true; loanForm.type = activeTab"
                        class="px-4 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                        id="add-loan-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Add New
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- KPI Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Total Receivable (Lent)</p>
                        <h4 class="text-3xl font-extrabold mt-1 text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(stats.total_receivable) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Money you lent to others and expect back</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Total Payable (Borrowed)</p>
                        <h4 class="text-3xl font-extrabold mt-1 text-rose-600 dark:text-rose-400">
                            {{ formatCurrency(stats.total_payable) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Money you borrowed and need to repay</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Overdue Accounts</p>
                        <h4 class="text-3xl font-extrabold mt-1" :class="stats.overdue_count > 0 ? 'text-amber-500 animate-pulse' : 'text-slate-700 dark:text-slate-350'">
                            {{ stats.overdue_count }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Active loans past their due dates</span>
                    </div>
                </div>

                <!-- Tabs & Controls -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-2 p-1 bg-slate-200/60 dark:bg-slate-900 rounded-xl max-w-max">
                        <button 
                            @click="activeTab = 'lent'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'lent' ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            Money I Lent (Receivable)
                        </button>
                        <button 
                            @click="activeTab = 'borrowed'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'borrowed' ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            Money I Borrowed (Payable)
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="status-filter" class="text-xs font-semibold text-slate-450 dark:text-slate-600 uppercase tracking-wider">Status:</label>
                        <select 
                            id="status-filter"
                            v-model="statusFilter"
                            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-850 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="active">Active Only</option>
                            <option value="repaid">Repaid Only</option>
                            <option value="all">All Loans</option>
                        </select>
                    </div>
                </div>

                <!-- Active Loans Table -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        {{ activeTab === 'lent' ? 'Outstanding Monies Lent' : 'Outstanding Monies Borrowed' }}
                    </h3>

                    <div v-if="filteredLoans.length === 0" class="py-16 text-center">
                        <div class="bg-indigo-50 dark:bg-slate-950 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-slate-700 dark:text-slate-600 font-medium">No records found matching filters.</p>
                        <p class="text-slate-600 dark:text-slate-700 text-xs mt-1">Click "New Loan / Debt" above to log a new entry.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-4">{{ activeTab === 'lent' ? 'Borrower (Debtor)' : 'Creditor (Lender)' }}</th>
                                    <th class="py-3 px-4 text-right">Initial Amount</th>
                                    <th class="py-3 px-4 text-right">Outstanding Balance</th>
                                    <th class="py-3 px-4 text-center">Due Date</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr 
                                    v-for="loan in filteredLoans" 
                                    :key="loan.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100">
                                        <div>{{ loan.person_name }}</div>
                                        <div v-if="loan.description" class="text-xs font-normal text-slate-600 mt-0.5 max-w-xs truncate">{{ loan.description }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-medium">
                                        {{ formatCurrency(loan.amount) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold" :class="loan.outstanding_balance > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-600 line-through'">
                                        {{ formatCurrency(loan.outstanding_balance) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span 
                                            v-if="loan.due_date" 
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                            :class="loan.is_overdue 
                                                ? 'bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-450 border border-rose-100 dark:border-rose-800' 
                                                : 'text-slate-700 dark:text-slate-600 bg-slate-100 dark:bg-slate-700'"
                                        >
                                            {{ loan.due_date }}
                                        </span>
                                        <span v-else class="text-slate-600 dark:text-slate-550 text-xs">No Due Date</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span 
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider inline-block"
                                            :class="loan.status === 'active' 
                                                ? 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-450' 
                                                : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-450'"
                                        >
                                            {{ loan.status }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button 
                                                v-if="loan.status === 'active'"
                                                @click="openRepaymentModal(loan)"
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition shadow-sm"
                                            >
                                                Log Payment
                                            </button>
                                            <button 
                                                @click="deleteLoan(loan.id)"
                                                class="p-1.5 bg-rose-50 dark:bg-rose-950/20 text-rose-500 hover:text-rose-700 hover:bg-rose-100 dark:hover:bg-rose-900/30 rounded-lg transition"
                                                title="Delete loan and clear history"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Repayment Ledger -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6M12 9v6m2 5H10a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v10a3 3 0 01-3 3z" />
                        </svg>
                        Repayment / Installment Ledger
                    </h3>

                    <div v-if="repayments.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700">
                        <p class="font-medium">No repayments logged yet.</p>
                        <p class="text-xs mt-1">Re-payments logged against active loans will populate this history.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Debtor / Creditor</th>
                                    <th class="py-3 px-4">Loan Relationship</th>
                                    <th class="py-3 px-4 text-right">Repaid Amount</th>
                                    <th class="py-3 px-4">Funding Account</th>
                                    <th class="py-3 px-4">Description / Memo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr v-for="pay in repayments" :key="pay.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                    <td class="py-3 px-4 font-semibold text-slate-700 dark:text-slate-600">
                                        {{ pay.transaction_date }}
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">
                                        {{ pay.loan_person_name }}
                                    </td>
                                    <td class="py-3 px-4 text-xs font-semibold">
                                        <span 
                                            class="px-2 py-0.5 rounded-md border"
                                            :class="pay.loan_type === 'lent' 
                                                ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-100 dark:border-emerald-900 text-emerald-600'
                                                : 'bg-rose-50 dark:bg-rose-950/20 border-rose-100 dark:border-rose-900 text-rose-600'"
                                        >
                                            {{ pay.loan_type === 'lent' ? 'Lent (Receiving Pay)' : 'Borrowed (Paying Back)' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-black" :class="pay.loan_type === 'lent' ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ pay.loan_type === 'lent' ? '+' : '-' }}{{ formatCurrency(pay.amount) }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div v-if="pay.account" class="flex items-center gap-1.5">
                                            <span 
                                                class="w-2.5 h-2.5 rounded-full inline-block shrink-0" 
                                                :style="{ backgroundColor: pay.account.color || '#6366f1' }"
                                            ></span>
                                            <span class="font-medium text-xs">{{ pay.account.name }}</span>
                                        </div>
                                        <span v-else class="text-xs text-slate-405 dark:text-slate-700">Unspecified</span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-700 dark:text-slate-600 text-xs italic">
                                        {{ pay.description || 'No description' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- CREATE LOAN / DEBT MODAL -->
        <div v-if="showLoanModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            Add Loan / Debt Record
                        </h3>
                        <button 
                            @click="showLoanModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitLoan">
                        <div class="p-6 space-y-4">
                            <!-- Person Name -->
                            <div>
                                <label for="loan-person" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Person Name</label>
                                <input 
                                    type="text" 
                                    id="loan-person" 
                                    v-model="loanForm.person_name"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Enter Debtor or Creditor name"
                                    required
                                />
                                <div v-if="loanForm.errors.person_name" class="text-rose-500 text-xs mt-1">{{ loanForm.errors.person_name }}</div>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Loan Type</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button 
                                        type="button"
                                        @click="loanForm.type = 'lent'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition"
                                        :class="loanForm.type === 'lent' 
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-slate-950 dark:border-emerald-500 dark:text-emerald-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                    >
                                        I Lent Money
                                    </button>
                                    <button 
                                        type="button"
                                        @click="loanForm.type = 'borrowed'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition"
                                        :class="loanForm.type === 'borrowed' 
                                            ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-slate-950 dark:border-rose-500 dark:text-rose-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                    >
                                        I Borrowed Money
                                    </button>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label for="loan-amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Principal Amount (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="loan-amount" 
                                    v-model="loanForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="0.00"
                                    required
                                    min="0.01"
                                />
                                <div v-if="loanForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ loanForm.errors.amount }}</div>
                            </div>

                            <!-- Source / Deposit Account -->
                            <div>
                                <label for="loan-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                    {{ loanForm.type === 'lent' ? 'Funding Account' : 'Deposit Account' }} (Optional)
                                </label>
                                <select 
                                    id="loan-account" 
                                    v-model="loanForm.account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">None (Don't affect any account balance)</option>
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} (Current: {{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <span class="text-[10px] text-slate-600 dark:text-slate-700 mt-1 block">
                                    {{ loanForm.type === 'lent' 
                                        ? 'Will log an Expense transaction of this amount to decrease account balance.' 
                                        : 'Will log an Income transaction of this amount to increase account balance.' }}
                                </span>
                                <div v-if="loanForm.errors.account_id" class="text-rose-500 text-xs mt-1">{{ loanForm.errors.account_id }}</div>
                            </div>

                            <!-- Due Date -->
                            <div>
                                <label for="loan-due" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Due Date (Optional)</label>
                                <input 
                                    type="date" 
                                    id="loan-due" 
                                    v-model="loanForm.due_date"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <div v-if="loanForm.errors.due_date" class="text-rose-500 text-xs mt-1">{{ loanForm.errors.due_date }}</div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="loan-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Description Notes (Optional)</label>
                                <textarea 
                                    id="loan-desc" 
                                    v-model="loanForm.description"
                                    rows="2"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Add notes or agreement descriptions..."
                                ></textarea>
                                <div v-if="loanForm.errors.description" class="text-rose-500 text-xs mt-1">{{ loanForm.errors.description }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 gap-3">
                            <button type="button" @click="showLoanModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-sm rounded-xl transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition" :disabled="loanForm.processing">
                                {{ loanForm.processing ? 'Creating...' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- LOG REPAYMENT MODAL -->
        <div v-if="showRepaymentModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            Log Loan Repayment / Installment
                        </h3>
                        <button 
                            @click="showRepaymentModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitRepayment">
                        <div class="p-6 space-y-4">
                            <!-- Recipient Details Info -->
                            <div class="p-4 bg-indigo-50/60 dark:bg-slate-950/60 border border-indigo-100/50 dark:border-slate-800 rounded-xl">
                                <p class="text-xs text-slate-600 font-semibold uppercase">Loan Information</p>
                                <p class="text-sm font-bold text-slate-850 dark:text-slate-200 mt-1">
                                    {{ selectedLoanForRepayment?.person_name }}
                                </p>
                                <p class="text-xs text-slate-700 dark:text-slate-600 mt-0.5">
                                    Relationship: {{ selectedLoanForRepayment?.type === 'lent' ? 'I Lent Money' : 'I Borrowed Money' }}
                                </p>
                                <p class="text-xs text-indigo-650 dark:text-indigo-400 font-bold mt-1">
                                    Outstanding Balance: {{ formatCurrency(selectedLoanForRepayment?.outstanding_balance) }}
                                </p>
                            </div>

                            <!-- Repayment Amount -->
                            <div>
                                <label for="repay-amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Repayment Amount (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="repay-amount" 
                                    v-model="repaymentForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="0.00"
                                    required
                                    min="0.01"
                                />
                                <div v-if="repaymentForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ repaymentForm.errors.amount }}</div>
                            </div>

                            <!-- Deposit / Funding Account -->
                            <div>
                                <label for="repay-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                    {{ selectedLoanForRepayment?.type === 'lent' ? 'Deposit Account (Receiving Funds)' : 'Source Account (Paying From)' }}
                                </label>
                                <select 
                                    id="repay-account" 
                                    v-model="repaymentForm.account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="" disabled>Choose Account...</option>
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} (Current: {{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <div v-if="repaymentForm.errors.account_id" class="text-rose-500 text-xs mt-1">{{ repaymentForm.errors.account_id }}</div>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label for="repay-date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Payment Date</label>
                                <input 
                                    type="date" 
                                    id="repay-date" 
                                    v-model="repaymentForm.transaction_date"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                />
                                <div v-if="repaymentForm.errors.transaction_date" class="text-rose-500 text-xs mt-1">{{ repaymentForm.errors.transaction_date }}</div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="repay-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Description / Memo Notes (Optional)</label>
                                <textarea 
                                    id="repay-desc" 
                                    v-model="repaymentForm.description"
                                    rows="2"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g. cash, installment 1, bank transfer, etc..."
                                ></textarea>
                                <div v-if="repaymentForm.errors.description" class="text-rose-500 text-xs mt-1">{{ repaymentForm.errors.description }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 gap-3">
                            <button type="button" @click="showRepaymentModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-sm rounded-xl transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl shadow-md transition" :disabled="repaymentForm.processing">
                                {{ repaymentForm.processing ? 'Saving...' : 'Log Repayment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
