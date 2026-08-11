<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { Chart } from 'chart.js/auto';

const props = defineProps({
    month: {
        type: String,
        required: true,
    },
    summary: {
        type: Object,
        required: true, // inflow, outflow, savings, savings_rate
    },
    expense_report: {
        type: Array,
        required: true,
    },
    income_report: {
        type: Array,
        required: true,
    },
    accounts_report: {
        type: Array,
        required: true,
    },
    transactions: {
        type: Array,
        required: true,
    },
});

const selectedMonth = ref(props.month);
const searchQuery = ref('');

const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

const formatMonthLabel = (value) => {
    if (!value) return '';
    const [year, month] = value.split('-');
    const date = new Date(year, month - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

const changeMonth = () => {
    router.get(route('reports.monthly'), { month: selectedMonth.value }, { preserveState: true });
};

// Filtered transaction list based on search bar query
const filteredTransactions = computed(() => {
    if (!searchQuery.value) return props.transactions;
    const query = searchQuery.value.toLowerCase();
    return props.transactions.filter(t => {
        return (t.description && t.description.toLowerCase().includes(query)) ||
            (t.category_name && t.category_name.toLowerCase().includes(query)) ||
            (t.account_name && t.account_name.toLowerCase().includes(query)) ||
            t.amount.toString().includes(query);
    });
});

// Computed properties for Monthly Budget totals
const totalExpenseReport = computed(() => {
    return props.expense_report.reduce((totals, cat) => {
        totals.spent += cat.spent || 0;
        totals.limit += cat.limit || 0;
        return totals;
    }, { spent: 0, limit: 0 });
});

const totalIncomeReport = computed(() => {
    return props.income_report.reduce((totals, cat) => {
        totals.earned += cat.earned || 0;
        totals.limit += cat.limit || 0;
        totals.deficit += cat.deficit || 0;
        return totals;
    }, { earned: 0, limit: 0, deficit: 0 });
});

const totalAccountsReport = computed(() => {
    return props.accounts_report.reduce((totals, acc) => {
        totals.inflows += acc.inflows || 0;
        totals.outflows += acc.outflows || 0;
        totals.net_flow += acc.net_flow || 0;
        return totals;
    }, { inflows: 0, outflows: 0, net_flow: 0 });
});

const totalSpent = computed(() => {
    return props.expense_report.reduce((sum, c) => sum + (c.spent || 0), 0);
});

const expenseShares = computed(() => {
    const total = totalSpent.value || 1;
    return props.expense_report
        .filter(c => (c.spent || 0) > 0)
        .map(c => ({
            ...c,
            share: parseFloat(((c.spent / total) * 100).toFixed(1))
        }))
        .sort((a, b) => b.spent - a.spent);
});

// Chart refs
const allocationCanvas = ref(null);
const sharesCanvas = ref(null);
const expenseComparisonCanvas = ref(null);
const incomeComparisonCanvas = ref(null);

let allocationChartInstance = null;
let sharesChartInstance = null;
let expenseChartInstance = null;
let incomeChartInstance = null;

const renderCharts = () => {
    // Destroy previous instances to avoid rendering glitches
    if (allocationChartInstance) allocationChartInstance.destroy();
    if (sharesChartInstance) sharesChartInstance.destroy();
    if (expenseChartInstance) expenseChartInstance.destroy();
    if (incomeChartInstance) incomeChartInstance.destroy();

    const labelColor = '#64748b'; // slate-500 works perfectly on both light & dark modes
    const gridLineColor = 'rgba(148, 163, 184, 0.12)'; // subtle slate gridline

    // 1. Allocation Doughnut Chart
    if (allocationCanvas.value) {
        const inflow = props.summary.inflow || 0;
        const savings = Math.max(0, props.summary.savings || 0);
        const outflow = props.summary.outflow || 0;
        allocationChartInstance = new Chart(allocationCanvas.value, {
            type: 'doughnut',
            data: {
                labels: ['Expenses', 'Savings'],
                datasets: [{
                    data: [outflow, savings],
                    backgroundColor: ['#f43f5e', '#10b981'],
                    borderWidth: 2,
                    borderColor: '#0f172a' // clean border spacing
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            padding: 12,
                            color: labelColor,
                            font: { size: 12.5, weight: 'bold', family: 'system-ui' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const val = context.raw;
                                const total = inflow || 1;
                                const pct = ((val / total) * 100).toFixed(0);
                                return `${context.label}: ${formatCurrency(val)} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Category Shares Bar Chart
    if (sharesCanvas.value && expenseShares.value.length > 0) {
        sharesChartInstance = new Chart(sharesCanvas.value, {
            type: 'bar',
            data: {
                labels: expenseShares.value.map(c => c.name),
                datasets: [{
                    label: 'Spent',
                    data: expenseShares.value.map(c => c.spent),
                    backgroundColor: expenseShares.value.map(c => c.color || '#6366f1'),
                    borderRadius: 4,
                    barThickness: 12
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: gridLineColor },
                        ticks: { color: labelColor, font: { size: 12.5, weight: 'semibold' } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: labelColor, font: { size: 12.5, weight: 'bold' } }
                    }
                }
            }
        });
    }

    // 3. Expense Budget Grouped Chart
    if (expenseComparisonCanvas.value && props.expense_report.length > 0) {
        expenseChartInstance = new Chart(expenseComparisonCanvas.value, {
            type: 'bar',
            data: {
                labels: props.expense_report.map(c => c.name),
                datasets: [
                    {
                        label: 'Spent',
                        data: props.expense_report.map(c => c.spent || 0),
                        backgroundColor: '#f43f5e',
                        borderRadius: 3,
                        barThickness: 10
                    },
                    {
                        label: 'Limit',
                        data: props.expense_report.map(c => c.limit || 0),
                        backgroundColor: '#94a3b8',
                        borderRadius: 3,
                        barThickness: 10
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 8,
                            padding: 8,
                            color: labelColor,
                            font: { size: 12.5, weight: 'bold' }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor, font: { size: 12.5, weight: 'semibold' } }
                    },
                    y: {
                        grid: { color: gridLineColor },
                        ticks: { color: labelColor, font: { size: 12.5 } }
                    }
                }
            }
        });
    }

    // 4. Income Target Grouped Chart
    if (incomeComparisonCanvas.value && props.income_report.length > 0) {
        incomeChartInstance = new Chart(incomeComparisonCanvas.value, {
            type: 'bar',
            data: {
                labels: props.income_report.map(c => c.name),
                datasets: [
                    {
                        label: 'Earned',
                        data: props.income_report.map(c => c.earned || 0),
                        backgroundColor: '#10b981',
                        borderRadius: 3,
                        barThickness: 10
                    },
                    {
                        label: 'Target',
                        data: props.income_report.map(c => c.limit || 0),
                        backgroundColor: '#94a3b8',
                        borderRadius: 3,
                        barThickness: 10
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 8,
                            padding: 8,
                            color: labelColor,
                            font: { size: 12.5, weight: 'bold' }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor, font: { size: 10.5, weight: 'semibold' } }
                    },
                    y: {
                        grid: { color: gridLineColor },
                        ticks: { color: labelColor, font: { size: 12.5 } }
                    }
                }
            }
        });
    }
};

onMounted(() => {
    renderCharts();
});

onBeforeUnmount(() => {
    if (allocationChartInstance) allocationChartInstance.destroy();
    if (sharesChartInstance) sharesChartInstance.destroy();
    if (expenseChartInstance) expenseChartInstance.destroy();
    if (incomeChartInstance) incomeChartInstance.destroy();
});

watch(() => props.month, () => {
    setTimeout(renderCharts, 80);
});
</script>

<template>
    <Head :title="`Monthly Report - ${formatMonthLabel(month)}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z" />
                        </svg>
                        Monthly Financial Report
                    </h2>
                    <p class="text-xs text-slate-600 dark:text-slate-700 mt-1">
                        Performance summary for the month of {{ formatMonthLabel(month) }}
                    </p>
                </div>

                <!-- Month Search Switcher -->
                <div class="flex items-center gap-3 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-2 rounded-xl shadow-xs">
                    <label for="month-select" class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 px-2">Choose Month:</label>
                    <input 
                        type="month" 
                        id="month-select" 
                        v-model="selectedMonth" 
                        @change="changeMonth"
                        class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>
        </template>

        <div class="py-8 text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- Overview Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Income -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Monthly Income (Inflow)</p>
                        <h4 class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(summary.inflow) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Earned during the month</span>
                    </div>

                    <!-- Total Expenses -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Monthly Expenses (Outflow)</p>
                        <h4 class="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400">
                            {{ formatCurrency(summary.outflow) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Spent during the month</span>
                    </div>

                    <!-- Net Savings -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Net Monthly Savings</p>
                        <h4 class="text-2xl font-bold mt-1" :class="summary.savings >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455'">
                            {{ summary.savings >= 0 ? '+' : '' }}{{ formatCurrency(summary.savings) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Income minus expenses</span>
                    </div>

                    <!-- Savings Rate -->
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Savings Rate</p>
                        <h4 class="text-2xl font-bold mt-1 text-indigo-600 dark:text-indigo-400">
                            {{ summary.savings_rate }}%
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Target Rate: 20%+</span>
                    </div>
                </div>
                <!-- Visual Budget & Expense Analysis Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Interactive Doughnut Chart: Income Allocation -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col items-center justify-between min-h-[340px]">
                        <div class="w-full flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                </svg>
                                Income Allocation & Savings Rate
                            </h3>
                        </div>

                        <!-- Doughnut Graphic Container -->
                        <div class="relative w-48 h-48 flex items-center justify-center my-4">
                            <canvas ref="allocationCanvas"></canvas>
                            <!-- Center Absolute Details -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 pointer-events-none">
                                <span class="text-[7px] uppercase tracking-wider font-extrabold text-slate-400">Savings Rate</span>
                                <span class="text-xs font-black mt-0.5 text-slate-800 dark:text-slate-100">{{ summary.savings_rate }}%</span>
                                <span class="text-[7px] text-slate-500 font-bold mt-0.5">Target: 20%+</span>
                            </div>
                        </div>
                    </div>

                    <!-- Category Expense Share Distribution (Horizontal Bars) -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 min-h-[340px] flex flex-col justify-between">
                        <div class="w-full h-full flex flex-col">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-2 uppercase tracking-wider flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Category Expense Shares
                            </h3>
                            <p class="text-[10px] text-slate-500 font-semibold mb-4">Percentage share of each category in total actual monthly expenses.</p>
                            
                            <div v-if="expenseShares.length === 0" class="py-12 text-center text-slate-555 dark:text-slate-600 italic font-semibold text-sm">
                                No expense categories configured or spent.
                            </div>
                            
                            <div v-else class="flex-1 relative min-h-[200px]">
                                <canvas ref="sharesCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Target vs Actual Bar Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Expense Budgets Actual vs Limit Bar Chart -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between min-h-[360px]">
                        <div class="w-full h-full flex flex-col">
                            <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2 mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z" />
                                </svg>
                                Expenses: Actual vs. Limit
                            </h3>
                            <p class="text-[10px] text-slate-500 font-semibold mb-4">Vertical comparison of actual monthly spent vs budget limit.</p>

                            <div v-if="expense_report.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-600 italic font-semibold text-sm">
                                No expense categories configured.
                            </div>

                            <div v-else class="flex-1 relative min-h-[220px]">
                                <canvas ref="expenseComparisonCanvas"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive Income Targets vs Actual Bar Chart -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between min-h-[360px]">
                        <div class="w-full h-full flex flex-col">
                            <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2 mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z" />
                                </svg>
                                Income: Actual vs. Target
                            </h3>
                            <p class="text-[10px] text-slate-500 font-semibold mb-4">Vertical comparison of actual monthly earned vs target goals.</p>

                            <div v-if="income_report.length === 0" class="py-12 text-center text-slate-500 dark:text-slate-600 italic font-semibold text-sm">
                                No income categories configured.
                            </div>

                            <div v-else class="flex-1 relative min-h-[220px]">
                                <canvas ref="incomeComparisonCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Budgets & Income Targets Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Expense Budgets Breakdown -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Expense Budgets Performance
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-700 mb-6 font-medium">
                            Budget limits vs actual spent per expense category.
                        </p>

                        <div v-if="expense_report.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                            No expense categories configured.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                        <th class="py-3 px-4">Category</th>
                                        <th class="py-3 px-4 text-right">Spent</th>
                                        <th class="py-3 px-4 text-right">Limit</th>
                                        <th class="py-3 px-4 text-right">Progress</th>
                                        <th class="py-3 px-4 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                    <tr v-for="cat in expense_report" :key="cat.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full inline-block flex-shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                                {{ cat.name }}
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-semibold text-slate-800 dark:text-slate-200">
                                            {{ formatCurrency(cat.spent) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-600 dark:text-slate-600 font-semibold">
                                            {{ cat.limit > 0 ? formatCurrency(cat.limit) : 'N/A' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-medium whitespace-nowrap">
                                            <div v-if="cat.limit > 0" class="flex items-center justify-end gap-2">
                                                <div class="w-16 bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full transition-all" :class="cat.percentage > 100 ? 'bg-rose-500' : 'bg-indigo-500'" :style="{ width: `${Math.min(cat.percentage, 100)}%` }"></div>
                                                </div>
                                                <span class="text-[10px] font-bold">{{ cat.percentage }}%</span>
                                            </div>
                                            <span v-else class="text-xs text-slate-605 italic">-</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <span 
                                                class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                                :class="cat.status === 'Over Limit' ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-455' : (cat.status === 'Compliant' ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400' : 'bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400')"
                                            >
                                                {{ cat.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <!-- Expense Totals Row -->
                                    <tr class="bg-slate-50/50 dark:bg-slate-900/50 font-black border-t border-slate-200 dark:border-slate-800">
                                        <td class="py-3.5 px-4 text-slate-850 dark:text-slate-100">Total Summary</td>
                                        <td class="py-3.5 px-4 text-right font-bold" :class="totalExpenseReport.limit > 0 && totalExpenseReport.spent > totalExpenseReport.limit ? 'text-rose-600 dark:text-rose-455' : 'text-slate-800 dark:text-slate-200'">
                                            {{ formatCurrency(totalExpenseReport.spent) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-600 dark:text-slate-400 font-semibold">{{ totalExpenseReport.limit > 0 ? formatCurrency(totalExpenseReport.limit) : 'N/A' }}</td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span v-if="totalExpenseReport.limit > 0" class="text-xs font-black">{{ (totalExpenseReport.spent / totalExpenseReport.limit * 100).toFixed(0) }}% Used</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span 
                                                class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                                :class="totalExpenseReport.limit > 0 ? (totalExpenseReport.spent > totalExpenseReport.limit ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-455' : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400') : 'bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400'"
                                            >
                                                {{ totalExpenseReport.limit > 0 ? (totalExpenseReport.spent > totalExpenseReport.limit ? 'Over Limit' : 'Compliant') : 'No Budget' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Income Targets Breakdown -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M12 14a2 2 0 110-4h4" />
                            </svg>
                            Income Targets Performance
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-700 mb-6 font-medium">
                            Earnings targets vs actual earned per income category.
                        </p>

                        <div v-if="income_report.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                            No income categories configured.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                        <th class="py-3 px-4">Category</th>
                                        <th class="py-3 px-4 text-right">Earned</th>
                                        <th class="py-3 px-4 text-right">Target</th>
                                        <th class="py-3 px-4 text-right">Deficit</th>
                                        <th class="py-3 px-4 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                    <tr v-for="cat in income_report" :key="cat.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full inline-block flex-shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                                {{ cat.name }}
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-semibold text-slate-800 dark:text-slate-200">
                                            {{ formatCurrency(cat.earned) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-600 dark:text-slate-600 font-semibold">
                                            {{ cat.limit > 0 ? formatCurrency(cat.limit) : 'N/A' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-extrabold whitespace-nowrap" :class="cat.deficit > 0 ? 'text-rose-600 dark:text-rose-455' : 'text-emerald-600 dark:text-emerald-400'">
                                            {{ cat.deficit > 0 ? formatCurrency(cat.deficit) : '-' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <span 
                                                class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                                :class="cat.status === 'Met' ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400' : (cat.status === 'Deficit' ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-455' : 'bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400')"
                                            >
                                                {{ cat.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <!-- Income Totals Row -->
                                    <tr class="bg-slate-50/50 dark:bg-slate-900/50 font-black border-t border-slate-200 dark:border-slate-800">
                                        <td class="py-3.5 px-4 text-slate-850 dark:text-slate-100">Total Summary</td>
                                        <td class="py-3.5 px-4 text-right font-bold" :class="totalIncomeReport.limit > 0 && totalIncomeReport.earned < totalIncomeReport.limit ? 'text-amber-600 dark:text-amber-500' : 'text-emerald-600 dark:text-emerald-450'">
                                            {{ formatCurrency(totalIncomeReport.earned) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-805 dark:text-slate-200">{{ totalIncomeReport.limit > 0 ? formatCurrency(totalIncomeReport.limit) : 'N/A' }}</td>
                                        <td class="py-3.5 px-4 text-right text-rose-600 dark:text-rose-450 font-extrabold">{{ totalIncomeReport.deficit > 0 ? formatCurrency(totalIncomeReport.deficit) : '-' }}</td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span 
                                                class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                                :class="totalIncomeReport.limit > 0 ? (totalIncomeReport.earned >= totalIncomeReport.limit ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-455') : 'bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400'"
                                            >
                                                {{ totalIncomeReport.limit > 0 ? (totalIncomeReport.earned >= totalIncomeReport.limit ? 'Met' : 'Deficit') : 'No Target' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Account Monthly Performance -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Account Performance
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-700 mb-6 font-medium">
                        Cash flow inflows, outflows, and net totals per account for this month.
                    </p>

                    <div v-if="accounts_report.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                        No accounts registered.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                    <th class="py-3 px-4">Account</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4 text-right">Inflows</th>
                                    <th class="py-3 px-4 text-right">Outflows</th>
                                    <th class="py-3 px-4 text-right">Net Flow</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr v-for="acc in accounts_report" :key="acc.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full inline-block flex-shrink-0" :style="{ backgroundColor: acc.color }"></span>
                                            {{ acc.name }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs font-medium text-slate-700 dark:text-slate-600 uppercase tracking-wider whitespace-nowrap">
                                        {{ acc.type.replace('_', ' ') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-emerald-600 dark:text-emerald-400 font-bold">
                                        +{{ formatCurrency(acc.inflows) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-rose-600 dark:text-rose-455 font-bold">
                                        -{{ formatCurrency(acc.outflows) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-extrabold whitespace-nowrap" :class="acc.net_flow >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-405'">
                                        {{ acc.net_flow >= 0 ? '+' : '' }}{{ formatCurrency(acc.net_flow) }}
                                    </td>
                                </tr>
                                <!-- Accounts Totals Row -->
                                <tr class="bg-slate-50/50 dark:bg-slate-900/50 font-black border-t border-slate-200 dark:border-slate-800">
                                    <td class="py-3.5 px-4 text-slate-850 dark:text-slate-100">Total Summary</td>
                                    <td class="py-3.5 px-4 text-xs font-medium text-slate-405 uppercase tracking-wider">All Accounts</td>
                                    <td class="py-3.5 px-4 text-right text-emerald-600 dark:text-emerald-400">+{{ formatCurrency(totalAccountsReport.inflows) }}</td>
                                    <td class="py-3.5 px-4 text-right text-rose-600 dark:text-rose-455">-{{ formatCurrency(totalAccountsReport.outflows) }}</td>
                                    <td class="py-3.5 px-4 text-right" :class="totalAccountsReport.net_flow >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-405'">
                                        {{ totalAccountsReport.net_flow >= 0 ? '+' : '' }}{{ formatCurrency(totalAccountsReport.net_flow) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Monthly Transactions Ledger & Local Search -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Search Transactions for this Month
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-700 mt-1 font-medium">
                                View and search all transaction line items logged during the selected month.
                            </p>
                        </div>

                        <!-- Search Bar -->
                        <div class="w-full md:w-80 relative">
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Search by description, category, account..."
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs font-bold text-slate-805 dark:text-slate-100 placeholder-slate-450 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div v-if="filteredTransactions.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                        No transactions match your search query for this month.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Account</th>
                                    <th class="py-3 px-4">Description</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr v-for="t in filteredTransactions" :key="t.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4 text-xs text-slate-650 dark:text-slate-400 font-semibold whitespace-nowrap">{{ t.transaction_date }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full inline-block flex-shrink-0" :style="{ backgroundColor: t.category_color }"></span>
                                            {{ t.category_name }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-700 dark:text-slate-600 whitespace-nowrap">{{ t.account_name }}</td>
                                    <td class="py-3 px-4 font-medium text-slate-700 dark:text-slate-350 max-w-xs truncate">{{ t.description || '-' }}</td>
                                    <td class="py-3 px-4 text-right font-black whitespace-nowrap" :class="t.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455'">
                                        {{ t.type === 'income' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
