<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    summary: Object,
    sparkline: Array,
    cashflow: Array,
    spending_categories: Array,
    accounts: Array,
    contacts: Array,
    recent_transactions: Array,
    reminders: Array,
    daily_summary: Object,
    categories: Array
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

// State refs
const searchQuery = ref('');

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
const filteredTransactions = computed(() => {
    if (!props.recent_transactions) return [];
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.recent_transactions;
    return props.recent_transactions.filter(t => {
        return (t.description && t.description.toLowerCase().includes(query)) ||
               (t.category_name && t.category_name.toLowerCase().includes(query)) ||
               (t.account_name && t.account_name.toLowerCase().includes(query));
    });
});
const copiedCardId = ref(null);
const copyCardNumber = (acc) => {
    navigator.clipboard.writeText('•••• •••• •••• ' + acc.last4);
    copiedCardId.value = acc.id;
    setTimeout(() => {
        if (copiedCardId.value === acc.id) {
            copiedCardId.value = null;
        }
    }, 2000);
};

// Account creation & modal state
const showAccountModal = ref(false);
const isEditingAccount = ref(false);
const accountForm = useForm({
    id: null,
    name: '',
    type: 'bank',
    initial_balance: '',
    color: '#6366f1',
});

// Transaction creation & modal state
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

const openAddTransaction = () => {
    isEditingTransaction.value = false;
    transactionForm.reset();
    transactionForm.clearErrors();
    transactionForm.transaction_date = new Date().toISOString().split('T')[0];
    if (props.categories && props.categories.length > 0) {
        // default category
        const expenseCats = props.categories.filter(c => c.type === 'expense');
        transactionForm.category_id = expenseCats.length > 0 ? expenseCats[0].id : '';
    }
    if (props.accounts && props.accounts.length > 0) {
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

const openEditTransaction = (tx) => {
    isEditingTransaction.value = true;
    transactionForm.id = tx.id;
    transactionForm.amount = tx.amount;
    transactionForm.type = tx.is_transfer ? 'transfer' : tx.type;
    
    // Find category ID by name matching
    const matchedCategory = props.categories.find(c => c.name === tx.category_name);
    transactionForm.category_id = matchedCategory ? matchedCategory.id : '';

    // Find account ID by name matching
    const matchedAccount = props.accounts.find(a => a.name === tx.account_name);
    transactionForm.account_id = matchedAccount ? matchedAccount.id : '';
    transactionForm.from_account_id = matchedAccount ? matchedAccount.id : '';
    
    const matchedToAccount = props.accounts.find(a => a.name === (tx.transfer_account?.name || ''));
    transactionForm.to_account_id = matchedToAccount ? matchedToAccount.id : '';
    
    // Parse date (it is formatted as "M d, Y" e.g. "Aug 12, 2026")
    if (tx.date) {
        try {
            const parsedDate = new Date(tx.date);
            if (!isNaN(parsedDate)) {
                const year = parsedDate.getFullYear();
                const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
                const day = String(parsedDate.getDate()).padStart(2, '0');
                transactionForm.transaction_date = `${year}-${month}-${day}`;
            } else {
                transactionForm.transaction_date = new Date().toISOString().split('T')[0];
            }
        } catch (e) {
            transactionForm.transaction_date = new Date().toISOString().split('T')[0];
        }
    } else {
        transactionForm.transaction_date = new Date().toISOString().split('T')[0];
    }
    
    transactionForm.description = tx.description;
    transactionForm.clearErrors();
    showTransactionModal.value = true;
};

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

const deleteTransaction = (id) => {
    if (confirm('Are you sure you want to delete this transaction?')) {
        router.delete(route('transactions.destroy', id), {
            preserveScroll: true
        });
    }
};

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

const selectAccountColor = (c) => {
    accountForm.color = c;
};

const submitAccount = () => {
    if (isEditingAccount.value) {
        accountForm.patch(route('accounts.update', accountForm.id), {
            onSuccess: () => {
                showAccountModal.value = false;
                accountForm.reset();
            }
        });
    } else {
        accountForm.post(route('accounts.store'), {
            onSuccess: () => {
                showAccountModal.value = false;
                accountForm.reset();
            }
        });
    }
};

const deleteAccount = (id) => {
    if (confirm('Are you sure you want to delete this account? All associated transactions will be deleted.')) {
        router.delete(route('accounts.destroy', id));
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

const getAccountTypeName = (type) => {
    const types = {
        bank: 'Bank Account',
        mobile_wallet: 'Mobile Wallet',
        cash: 'Cash / Wallet',
        credit_card: 'Credit Card',
        other: 'Other Account'
    };
    return types[type] || 'Financial Account';
};

// Canvas refs
const sparklineCanvas = ref(null);
const cashflowCanvas = ref(null);
const spendingCanvas = ref(null);

let sparklineChart = null;
let cashflowChart = null;
let spendingChart = null;

const cashflowIncomeVisible = ref(true);
const cashflowExpenseVisible = ref(true);

const toggleCashflowSeries = (seriesIndex) => {
    if (!cashflowChart) return;
    const isVisible = cashflowChart.isDatasetVisible(seriesIndex);
    if (seriesIndex === 0) {
        cashflowIncomeVisible.value = !isVisible;
    } else {
        cashflowExpenseVisible.value = !isVisible;
    }
    cashflowChart.setDatasetVisibility(seriesIndex, !isVisible);
    cashflowChart.update();
};

onMounted(() => {
    // 1. Sparkline Chart
    if (sparklineCanvas.value) {
        const ctx = sparklineCanvas.value.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 80);
        grad.addColorStop(0, 'rgba(56, 189, 248, 0.25)');
        grad.addColorStop(1, 'rgba(56, 189, 248, 0)');

        sparklineChart = new Chart(sparklineCanvas.value, {
            type: 'line',
            data: {
                labels: props.sparkline.map(d => d.date),
                datasets: [{
                    data: props.sparkline.map(d => d.balance),
                    borderColor: '#38bdf8',
                    borderWidth: 2,
                    fill: true,
                    backgroundColor: grad,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });
    }

    // 2. Cashflow Chart
    if (cashflowCanvas.value) {
        const ctx = cashflowCanvas.value.getContext('2d');
        const gradIncome = ctx.createLinearGradient(0, 0, 0, 200);
        gradIncome.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
        gradIncome.addColorStop(1, 'rgba(16, 185, 129, 0)');

        const gradExpense = ctx.createLinearGradient(0, 0, 0, 200);
        gradExpense.addColorStop(0, 'rgba(239, 68, 68, 0.12)');
        gradExpense.addColorStop(1, 'rgba(239, 68, 68, 0)');

        cashflowChart = new Chart(cashflowCanvas.value, {
            type: 'line',
            data: {
                labels: props.cashflow.map(d => d.month),
                datasets: [
                    {
                        label: 'Income',
                        data: props.cashflow.map(d => d.income),
                        borderColor: '#10b981',
                        borderWidth: 2.5,
                        backgroundColor: gradIncome,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Expense',
                        data: props.cashflow.map(d => d.expense),
                        borderColor: '#ef4444',
                        borderWidth: 2.5,
                        backgroundColor: gradExpense,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10, weight: 'semibold' } }
                    },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.08)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    }
                }
            }
        });
    }

    // 3. Spending Chart
    if (spendingCanvas.value) {
        spendingChart = new Chart(spendingCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.spending_categories.map(c => c.name),
                datasets: [{
                    data: props.spending_categories.map(c => c.value),
                    backgroundColor: props.spending_categories.map(c => c.color),
                    borderWidth: 2,
                    borderColor: '#0f172a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 8,
                            padding: 10,
                            font: { size: 10, weight: 'bold' }
                        }
                    }
                }
            }
        });
    }
});

onBeforeUnmount(() => {
    if (sparklineChart) sparklineChart.destroy();
    if (cashflowChart) cashflowChart.destroy();
    if (spendingChart) spendingChart.destroy();
});
</script>

<template>
    <Head title="Finance Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        Finance Dashboard
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        TailAdmin-inspired advanced wealth overview and asset manager.
                    </p>
                </div>
                <!-- Filters Group -->
                <div class="flex items-center gap-3">
                    <button 
                        @click="openAddTransaction"
                        class="px-4 py-2 bg-indigo-650 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Transaction
                    </button>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-slate-100 dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800">
                        Live Feed
                    </span>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
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

            <!-- Today's Daily Summary Diagnostic Panel -->
            <div v-if="daily_summary" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm mb-8 animate-fade-in">
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
                    <Link 
                        :href="route('reports.daily')" 
                        class="text-xs font-black text-indigo-600 dark:text-indigo-400 hover:underline uppercase tracking-wider flex items-center gap-1 shrink-0"
                    >
                        View Full Daily Report 
                        <svg xmlns="http://www.w3.org/2050/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
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
                            :class="daily_summary.cumulative_savings >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455'"
                        >
                            {{ formatCurrency(daily_summary.cumulative_savings) }}
                        </span>
                        <span class="text-[9px] text-slate-400 font-semibold mt-1 block">
                            Carryover surplus this month
                        </span>
                    </div>
                </div>
            </div>

            <!-- Top Section Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
                
                <!-- Left Card: Total Balance & sparkline -->
                <div class="xl:col-span-5 bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 text-white border-transparent rounded-3xl p-6 flex flex-col justify-between min-h-[350px] shadow-xl relative overflow-hidden group/total">
                    <!-- Absolute glow design -->
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none group-hover/total:scale-110 transition-transform duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-indigo-300 uppercase tracking-wider">Total Balance</h3>
                                <p class="text-xs text-indigo-200/60 font-semibold mt-0.5">Overview of your current funds</p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-1 bg-white/10 text-indigo-300 rounded-lg">
                                Realtime
                            </span>
                        </div>

                        <div class="flex items-end justify-between mt-8 pb-6 border-b border-dashed border-slate-800">
                            <div>
                                <h2 class="text-4xl font-black text-white tracking-tight">
                                    {{ formatCurrency(summary.total_balance) }}
                                </h2>
                                <p class="text-xs text-emerald-400 font-bold mt-1.5 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span>MoM Active Stability</span>
                                </p>
                            </div>
                            <!-- Sparkline Chart -->
                            <div class="w-36 h-14 relative">
                                <canvas ref="sparklineCanvas"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-indigo-200/70 font-semibold">Primary Account:</span>
                            <span class="text-sm font-bold text-white">
                                {{ accounts[0] ? accounts[0].name : 'N/A' }}
                            </span>
                        </div>
                        <div class="text-xs text-indigo-200/50 font-semibold">
                            Capped Overview
                        </div>
                    </div>
                </div>

                <!-- Right Side: 4 Stats Cards Grid -->
                <div class="xl:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    
                    <!-- Net Balance Stat -->
                    <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white border-transparent rounded-3xl p-6 flex flex-col justify-between min-h-[162px] shadow-lg relative overflow-hidden group/net">
                        <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/net:scale-110 transition-transform duration-500"></div>
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <p class="text-xs text-blue-100/80 font-bold uppercase tracking-wider">Net Assets</p>
                                <h3 class="text-2xl font-black text-white mt-1">
                                    {{ formatCurrency(summary.total_balance) }}
                                </h3>
                            </div>
                            <span class="bg-white/10 text-white p-2.5 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </span>
                        </div>
                        <p class="text-[10px] text-blue-100/70 font-semibold flex items-center gap-1.5 relative z-10">
                            <span class="text-emerald-300 font-bold flex items-center gap-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                </svg>
                                3.2%
                            </span>
                            <span>increase than last month</span>
                        </p>
                    </div>

                    <!-- Monthly Income Stat -->
                    <div class="bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 text-white border-transparent rounded-3xl p-6 flex flex-col justify-between min-h-[162px] shadow-lg relative overflow-hidden group/income">
                        <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/income:scale-110 transition-transform duration-500"></div>
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <p class="text-xs text-emerald-100/80 font-bold uppercase tracking-wider">Monthly Income</p>
                                <h3 class="text-2xl font-black text-white mt-1">
                                    {{ formatCurrency(summary.monthly_income) }}
                                </h3>
                            </div>
                            <span class="bg-white/10 text-white p-2.5 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </span>
                        </div>
                        <p class="text-[10px] text-emerald-100/70 font-semibold flex items-center gap-1.5 relative z-10">
                            <span class="font-bold flex items-center gap-0.5" :class="summary.income_change_pct >= 0 ? 'text-emerald-300' : 'text-rose-300'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                </svg>
                                {{ Math.abs(summary.income_change_pct) }}%
                            </span>
                            <span>than last month</span>
                        </p>
                    </div>

                    <!-- Total Spent Stat -->
                    <div class="bg-gradient-to-br from-rose-600 via-rose-700 to-red-800 text-white border-transparent rounded-3xl p-6 flex flex-col justify-between min-h-[162px] shadow-lg relative overflow-hidden group/spent">
                        <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/spent:scale-110 transition-transform duration-500"></div>
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <p class="text-xs text-rose-100/80 font-bold uppercase tracking-wider">Total Spent</p>
                                <h3 class="text-2xl font-black text-white mt-1">
                                    {{ formatCurrency(summary.monthly_expense) }}
                                </h3>
                            </div>
                            <span class="bg-white/10 text-white p-2.5 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                        </div>
                        <p class="text-[10px] text-rose-100/70 font-semibold flex items-center gap-1.5 relative z-10">
                            <span class="font-bold flex items-center gap-0.5" :class="summary.spent_change_pct <= 0 ? 'text-emerald-300' : 'text-rose-300'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                </svg>
                                {{ summary.spent_change_pct }}%
                            </span>
                            <span>spent change MoM</span>
                        </p>
                    </div>

                    <!-- Savings Rate Stat with gauge -->
                    <div class="bg-gradient-to-br from-purple-600 via-purple-700 to-violet-800 text-white border-transparent rounded-3xl p-6 flex flex-col justify-between min-h-[162px] shadow-lg relative overflow-hidden group/save">
                        <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/save:scale-110 transition-transform duration-500"></div>
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <p class="text-xs text-purple-100/80 font-bold uppercase tracking-wider">Saving Rate</p>
                                <h3 class="text-2xl font-black text-white mt-1">
                                    {{ summary.savings_rate }}%
                                </h3>
                            </div>
                            <span class="bg-white/10 text-white p-2.5 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                        </div>
                        <div class="relative z-10">
                            <!-- Progress Bar -->
                            <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden mt-3">
                                <div class="bg-white h-full rounded-full transition-all duration-500" :style="{ width: Math.min(100, (summary.savings_rate / 30) * 100) + '%' }"></div>
                            </div>
                            <p class="text-[10px] text-purple-100 font-semibold mt-2.5">
                                Goal: 30% savings rate ({{ summary.savings_rate >= 30 ? 'Goal Met!' : (30 - summary.savings_rate).toFixed(1) + '% to go' }})
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Section Grid: Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cashflow Overview (Area chart comparing income/expense) -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Cashflow Overview
                            </h3>
                            <p class="text-[10px] text-slate-500 font-semibold mt-0.5">MoM cash inflows versus outflows trend comparison</p>
                        </div>
                        
                        <!-- Interactive Toggle Badges -->
                        <div class="flex items-center gap-4">
                            <button 
                                @click="toggleCashflowSeries(0)"
                                class="flex items-center gap-1.5 text-xs font-bold transition-opacity"
                                :class="cashflowIncomeVisible ? 'opacity-100' : 'opacity-40'"
                            >
                                <span class="size-2.5 rounded-full bg-emerald-500"></span>
                                <span class="text-slate-700 dark:text-slate-300">Income</span>
                            </button>
                            <button 
                                @click="toggleCashflowSeries(1)"
                                class="flex items-center gap-1.5 text-xs font-bold transition-opacity"
                                :class="cashflowExpenseVisible ? 'opacity-100' : 'opacity-40'"
                            >
                                <span class="size-2.5 rounded-full bg-red-500"></span>
                                <span class="text-slate-700 dark:text-slate-300">Expense</span>
                            </button>
                        </div>
                    </div>

                    <!-- Line Chart container -->
                    <div class="flex-1 min-h-[260px] relative">
                        <canvas ref="cashflowCanvas"></canvas>
                    </div>
                </div>

                <!-- Spending Breakdown (Doughnut Chart) -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            Spending Shares
                        </h3>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">Where the monthly budget outflows are spent</p>
                    </div>

                    <div v-if="spending_categories.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-650 italic font-semibold text-xs">
                        No spending categories configured this month.
                    </div>

                    <div v-else class="flex-1 min-h-[190px] relative mt-4">
                        <canvas ref="spendingCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Section Grid: Account Cards (All visible) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            My Cards
                        </h3>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">User accounts transformed into credit card layouts</p>
                    </div>
                    <button 
                        @click="openAddAccount"
                        class="h-9 px-4 bg-indigo-650 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Account
                    </button>
                </div>

                <div v-if="accounts.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                    No financial accounts configured.
                </div>

                <!-- Cards Grid - All Visible -->
                <div 
                    v-else
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6 py-2"
                >
                    <div 
                        v-for="acc in accounts" 
                        :key="acc.id"
                        class="w-full h-[160px] rounded-2xl relative p-5 text-white flex flex-col justify-between shadow-lg border border-white/10 overflow-hidden group/card hover:scale-[1.02] transition-all duration-300"
                        :style="{ background: `linear-gradient(135deg, ${acc.color || '#6366f1'} 0%, #0f172a 100%)` }"
                    >
                        <!-- Card Header -->
                        <div class="flex justify-between items-start relative z-10 w-full">
                            <div>
                                <span class="text-[9px] font-bold opacity-60 uppercase tracking-widest">{{ getAccountTypeName(acc.type) }}</span>
                                <h4 class="text-sm font-bold truncate max-w-[150px]">{{ acc.name }}</h4>
                            </div>
                            <!-- Card Actions -->
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button 
                                    @click="copyCardNumber(acc)"
                                    class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-white transition-all flex items-center justify-center"
                                    title="Copy Card Number"
                                >
                                    <svg v-if="copiedCardId !== acc.id" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <button 
                                    @click="openEditAccount(acc)"
                                    class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-white transition-all flex items-center justify-center"
                                    title="Edit Account"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <button 
                                    @click="deleteAccount(acc.id)"
                                    class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-white transition-all flex items-center justify-center"
                                    title="Delete Account"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Card chip vector layout -->
                        <div class="w-8 h-6 bg-amber-400/25 rounded-md border border-amber-300/35 relative z-10 flex items-center justify-center">
                            <div class="w-4 h-3 bg-amber-400/40 rounded-sm"></div>
                        </div>

                        <!-- Card Footer -->
                        <div class="flex justify-between items-end relative z-10">
                            <div>
                                <span class="text-[8px] font-semibold opacity-60 uppercase block">Card Balance</span>
                                <span class="text-lg font-black tracking-tight">{{ formatCurrency(acc.balance) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-black block tracking-widest">•••• {{ acc.last4 }}</span>
                            </div>
                        </div>
                        
                        <!-- Design Circle Decorator Background -->
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/card:scale-110 transition-transform duration-500"></div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions Table Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            This Month's Transactions
                        </h3>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">Realtime view of account cash inflows and outflows</p>
                    </div>
                    <div class="flex items-center gap-4 flex-1 md:flex-none justify-end">
                        <!-- Search input -->
                        <div class="relative w-full max-w-[280px]">
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Search description, category..."
                                class="w-full h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-905 pl-9 pr-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 placeholder-slate-400 dark:placeholder-slate-650 transition-all"
                            />
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <Link
                            href="/transactions"
                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-350 transition-all flex items-center gap-1 shrink-0"
                        >
                            View All
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <div v-if="filteredTransactions.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                    No transactions matched your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 text-[10px] font-bold text-slate-400 uppercase tracking-wider pb-3">
                                <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Account</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Amount</th>
                                <th class="py-3 px-4 text-right w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                            <tr 
                                v-for="t in filteredTransactions" 
                                :key="t.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                <!-- Description -->
                                <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-200">
                                    {{ t.description }}
                                </td>
                                <!-- Category -->
                                <td class="py-3.5 px-4">
                                    <span 
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase text-white/90 shadow-sm"
                                        :style="{ backgroundColor: t.category_color }"
                                    >
                                        {{ t.category_name }}
                                    </span>
                                </td>
                                <!-- Account -->
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">
                                    {{ t.account_name }}
                                </td>
                                <!-- Date -->
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">
                                    {{ t.date }}
                                </td>
                                <!-- Amount -->
                                <td class="py-3.5 px-4 text-right font-black" :class="t.type === 'income' ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ t.type === 'income' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                                </td>
                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button 
                                            @click="openEditTransaction(t)"
                                            class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-indigo-650 dark:hover:text-indigo-400 rounded-lg transition-colors"
                                            title="Edit Transaction"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteTransaction(t.id)"
                                            class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-400 hover:text-rose-600 dark:hover:text-rose-455 rounded-lg transition-colors"
                                            title="Delete Transaction"
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
            </div>
        </div>

        <!-- Account Creator/Editor Modal -->
        <div v-if="showAccountModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative">
                <button 
                    @click="showAccountModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-base font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4">
                    {{ isEditingAccount ? 'Modify Account' : 'Create New Account' }}
                </h3>

                <form @submit.prevent="submitAccount" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Account Name
                        </label>
                        <input 
                            type="text" 
                            v-model="accountForm.name" 
                            placeholder="e.g. Citibank, bKash Wallet, Petty Cash..."
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="accountForm.errors.name" class="text-xs text-rose-500 mt-1 font-semibold">{{ accountForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Account Type
                        </label>
                        <select 
                            v-model="accountForm.type"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                        >
                            <option value="bank">Bank Account</option>
                            <option value="mobile_wallet">Mobile Wallet</option>
                            <option value="cash">Cash / Wallet</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other Account</option>
                        </select>
                        <p v-if="accountForm.errors.type" class="text-xs text-rose-500 mt-1 font-semibold">{{ accountForm.errors.type }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Initial Starting Balance (৳)
                        </label>
                        <input 
                            type="number" 
                            v-model="accountForm.initial_balance" 
                            placeholder="0.00"
                            step="0.01"
                            class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            required
                        />
                        <p v-if="accountForm.errors.initial_balance" class="text-xs text-rose-500 mt-1 font-semibold">{{ accountForm.errors.initial_balance }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                            Theme Visual Color
                        </label>
                        <div class="flex flex-wrap gap-2.5">
                            <button 
                                v-for="c in colors" 
                                :key="c"
                                type="button"
                                @click="accountForm.color = c"
                                class="w-8 h-8 rounded-full border-2 transition hover:scale-110"
                                :class="accountForm.color === c ? 'border-slate-900 dark:border-white scale-105' : 'border-transparent opacity-85'"
                                :style="{ backgroundColor: c }"
                            ></button>
                        </div>
                        <p v-if="accountForm.errors.color" class="text-xs text-rose-500 mt-1 font-semibold">{{ accountForm.errors.color }}</p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="accountForm.processing"
                        class="w-full h-11 bg-indigo-650 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        {{ isEditingAccount ? 'Modify Account Details' : 'Create New Account' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Transaction Creator/Editor Modal -->
        <div v-if="showTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="relative w-full max-w-lg mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            {{ isEditingTransaction ? 'Modify Transaction' : 'Log New Transaction' }}
                        </h3>
                        <button 
                            @click="showTransactionModal = false"
                            class="p-1 text-slate-650 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
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
                                            ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-rose-950/30 dark:border-rose-500 dark:text-rose-455' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
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
                                        {{ acc.name }} ({{ formatCurrency(acc.balance) }})
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
                                        {{ acc.name }} ({{ formatCurrency(acc.balance) }})
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
                                        {{ acc.name }} ({{ formatCurrency(acc.balance) }})
                                    </option>
                                </select>
                                <div v-if="transactionForm.errors.to_account_id" class="text-rose-500 text-xs mt-1">{{ transactionForm.errors.to_account_id }}</div>
                            </div>

                            <!-- Date -->
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
                                class="px-4 py-2 text-slate-700 dark:text-slate-655 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2.5 bg-indigo-650 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition duration-150"
                                :disabled="transactionForm.processing"
                            >
                                {{ transactionForm.processing ? 'Saving...' : 'Save Transaction' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
