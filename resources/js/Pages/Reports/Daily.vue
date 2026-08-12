<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    date: {
        type: String,
        required: true,
    },
    budget: {
        type: Object,
        required: true, // expense
    },
    actual: {
        type: Object,
        required: true, // expense
    },
    surplus: {
        type: Number,
        required: true,
    },
    cumulative_savings: {
        type: Number,
        required: true,
    },
    transactions: {
        type: Array,
        required: true,
    },
    daily_categories: {
        type: Array,
        default: () => [],
    },
    weekly_categories: {
        type: Array,
        default: () => [],
    },
    one_time_categories: {
        type: Array,
        default: () => [],
    },
    monthly_daily_summary: {
        type: Object,
        required: false,
    },
    daily_category_summary: {
        type: Array,
        default: () => [],
    },
});

const selectedDate = ref(props.date);

const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

const formatDateLabel = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const handleDateChange = () => {
    router.get(route('reports.daily'), { date: selectedDate.value }, { preserveState: true });
};

const spendingShareCanvas = ref(null);
const budgetComparisonCanvas = ref(null);
const pacingAuditCanvas = ref(null);
const activeBreakdownTab = ref('share');

let spendingShareChart = null;
let budgetComparisonChart = null;
let pacingAuditChart = null;

const initCharts = () => {
    if (spendingShareChart) spendingShareChart.destroy();
    if (budgetComparisonChart) budgetComparisonChart.destroy();
    if (pacingAuditChart) pacingAuditChart.destroy();

    // 1. Spending Share Doughnut Chart (Only build if daily categories exist)
    if (spendingShareCanvas.value && props.daily_category_summary && props.daily_category_summary.length > 0) {
        const spentCategories = props.daily_category_summary.filter(c => c.spent > 0);
        const displayData = spentCategories.length > 0 ? spentCategories : props.daily_category_summary;

        spendingShareChart = new Chart(spendingShareCanvas.value, {
            type: 'doughnut',
            data: {
                labels: displayData.map(c => c.name),
                datasets: [{
                    data: displayData.map(c => c.spent),
                    backgroundColor: displayData.map(c => c.color),
                    borderWidth: 2,
                    borderColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 8,
                            padding: 12,
                            font: { size: 10, weight: 'bold' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const val = context.raw;
                                return ` Spent: ${formatCurrency(val)}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Budget vs Spent Horizontal Bar Chart
    if (budgetComparisonCanvas.value && props.daily_category_summary && props.daily_category_summary.length > 0) {
        budgetComparisonChart = new Chart(budgetComparisonCanvas.value, {
            type: 'bar',
            data: {
                labels: props.daily_category_summary.map(c => c.name),
                datasets: [
                    {
                        label: 'Monthly Budget',
                        data: props.daily_category_summary.map(c => c.budget),
                        backgroundColor: 'rgba(99, 102, 241, 0.45)', // indigo
                        borderColor: '#6366f1',
                        borderWidth: 1.5,
                        borderRadius: 6
                    },
                    {
                        label: 'Spent So Far',
                        data: props.daily_category_summary.map(c => c.spent),
                        backgroundColor: 'rgba(239, 68, 68, 0.75)', // red
                        borderColor: '#ef4444',
                        borderWidth: 1.5,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: 'rgba(148, 163, 184, 0.08)' },
                        ticks: { color: '#94a3b8', font: { size: 10, weight: 'bold' } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10, weight: 'bold' } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 8,
                            padding: 12,
                            font: { size: 10, weight: 'bold' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const val = context.raw;
                                return ` ${context.dataset.label}: ${formatCurrency(val)}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 3. Today's Pacing & Surplus Audit Chart (Doughnut)
    if (pacingAuditCanvas.value) {
        const spentVal = parseFloat(props.actual.expense) || 0;
        const budgetVal = parseFloat(props.budget.expense) || 0;
        const surplusVal = parseFloat(props.surplus) || 0;

        // If overspent (deficit), display 100% spent on chart, else display breakdown
        const chartData = surplusVal >= 0 
            ? [spentVal, surplusVal] 
            : [spentVal, 0];
        
        const chartLabels = surplusVal >= 0 
            ? ['Spent', 'Surplus'] 
            : ['Spent (Over Limit)', 'Surplus'];

        pacingAuditChart = new Chart(pacingAuditCanvas.value, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        surplusVal >= 0 ? 'rgba(239, 68, 68, 0.85)' : 'rgba(220, 38, 38, 0.95)', // red / dark red
                        'rgba(16, 185, 129, 0.85)' // green
                    ],
                    borderWidth: 2,
                    borderColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff'
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
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const val = context.raw;
                                return ` ${context.label}: ${formatCurrency(val)}`;
                            }
                        }
                    }
                }
            }
        });
    }
};

onMounted(() => {
    initCharts();
});

watch(() => [props.daily_category_summary, props.budget, props.actual, props.surplus], () => {
    initCharts();
}, { deep: true });

onBeforeUnmount(() => {
    if (spendingShareChart) spendingShareChart.destroy();
    if (budgetComparisonChart) budgetComparisonChart.destroy();
    if (pacingAuditChart) pacingAuditChart.destroy();
});
</script>

<template>
    <Head title="Daily Summary Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        Daily Summary Report
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Detailed diagnostic breakdown of expense budgets vs. actual spending for any calendar day.
                    </p>
                </div>
                
                <!-- Date Picker Selector -->
                <div class="flex items-center gap-2 bg-white dark:bg-slate-900 p-2 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm shrink-0">
                    <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider dark:text-slate-400 pl-2">Select Date:</span>
                    <input 
                        type="date" 
                        v-model="selectedDate" 
                        @change="handleDateChange"
                        class="bg-transparent text-slate-800 dark:text-slate-100 font-bold border-none focus:ring-0 p-1 text-sm cursor-pointer outline-none"
                    />
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fade-in">
            <!-- Active Date Header Banner -->
            <div class="p-6 bg-gradient-to-tr from-slate-950 via-slate-900 to-indigo-950/60 rounded-3xl border border-slate-800 shadow-xl flex items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Active Report Day</span>
                    <h3 class="text-lg sm:text-xl font-black text-white mt-1">
                        {{ formatDateLabel(date) }}
                    </h3>
                </div>
                <div class="px-3.5 py-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-black uppercase tracking-wider">
                    ৳ Bangladeshi Taka
                </div>
            </div>

            <!-- Header Grid: Today vs Monthly Pacing -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left: Financial Pacing Overview & Audit Chart (Span 8) -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Today vs Month Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Today's Pacing Metrics -->
                        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm space-y-4">
                            <h4 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                                Today's Pacing Metrics
                            </h4>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Pacing Budget</span>
                                    <span class="text-sm font-black text-slate-855 dark:text-white mt-1 block">{{ formatCurrency(budget.expense) }}</span>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Spent</span>
                                    <span class="text-sm font-black text-rose-600 dark:text-rose-455 mt-1 block">{{ formatCurrency(actual.expense) }}</span>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Surplus</span>
                                    <span class="text-sm font-black mt-1 block" :class="surplus >= 0 ? 'text-emerald-600 dark:text-emerald-455' : 'text-rose-600 dark:text-rose-455'">
                                        {{ formatCurrency(surplus) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Daily-Expense Progress -->
                        <div v-if="monthly_daily_summary" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm space-y-4">
                            <h4 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                                Month-to-Date Progress
                            </h4>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Limit</span>
                                    <span class="text-sm font-black text-slate-855 dark:text-white mt-1 block">{{ formatCurrency(monthly_daily_summary.budget) }}</span>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Spent</span>
                                    <span class="text-sm font-black text-rose-600 dark:text-rose-455 mt-1 block">{{ formatCurrency(monthly_daily_summary.actual) }}</span>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100/50 dark:border-slate-850/50">
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold block">Remaining</span>
                                    <span class="text-sm font-black mt-1 block" :class="monthly_daily_summary.remaining >= 0 ? 'text-emerald-600 dark:text-emerald-455' : 'text-rose-605 dark:text-rose-455'">
                                        {{ formatCurrency(monthly_daily_summary.remaining) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Pacing & Surplus Chart Card -->
                    <div v-if="daily_category_summary && daily_category_summary.length > 0" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                <h3 class="text-sm font-black text-slate-855 dark:text-white uppercase tracking-wider mb-6 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-505" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    Daily Expenses Category Breakdown
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left: Category list with custom progress indicator cards (Span 5) -->
                    <div class="lg:col-span-5 space-y-4 max-h-[380px] overflow-y-auto pr-2">
                        <div 
                            v-for="cat in daily_category_summary" 
                            :key="cat.id"
                            class="p-4 rounded-2xl border border-slate-100 dark:border-slate-850 bg-slate-50/50 dark:bg-slate-950/40 hover:bg-slate-50 dark:hover:bg-slate-900 transition duration-200"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                    <span class="font-extrabold text-slate-800 dark:text-slate-200 text-xs">{{ cat.name }}</span>
                                </div>
                                <span 
                                    class="text-[10px] font-black"
                                    :class="cat.remaining >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455'"
                                >
                                    {{ cat.remaining >= 0 ? 'Remaining: ' + formatCurrency(cat.remaining) : 'Deficit: ' + formatCurrency(Math.abs(cat.remaining)) }}
                                </span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mt-3">
                                <div class="h-2 w-full bg-slate-150 dark:bg-slate-900 rounded-full overflow-hidden border border-slate-200/20">
                                    <div 
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="cat.spent > cat.budget ? 'bg-rose-500' : 'bg-indigo-500'"
                                        :style="{ width: `${cat.budget > 0 ? Math.min(100, (cat.spent / cat.budget) * 100) : 0}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between items-center text-[9px] text-slate-400 dark:text-slate-500 font-bold mt-1.5">
                                    <span>Spent: {{ formatCurrency(cat.spent) }}</span>
                                    <span>Budget: {{ formatCurrency(cat.budget) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Tabbed interactive charts (Span 7) -->
                    <div class="lg:col-span-7 flex flex-col justify-between p-6 bg-slate-50/50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-850/50 rounded-2xl">
                        <!-- Chart Tab Switcher -->
                        <div class="flex items-center gap-2 mb-6 bg-slate-150 dark:bg-slate-900 p-1.5 rounded-xl self-start border border-slate-200/20">
                            <button 
                                @click="activeBreakdownTab = 'share'"
                                class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="activeBreakdownTab === 'share' 
                                    ? 'bg-white dark:bg-slate-800 text-slate-855 dark:text-white shadow-sm' 
                                    : 'text-slate-455 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            >
                                Spending Share
                            </button>
                            <button 
                                @click="activeBreakdownTab = 'comparison'"
                                class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="activeBreakdownTab === 'comparison' 
                                    ? 'bg-white dark:bg-slate-800 text-slate-855 dark:text-white shadow-sm' 
                                    : 'text-slate-455 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            >
                                Budget vs Spent
                            </button>
                        </div>

                        <!-- Charts Canvas Container -->
                        <div class="h-64 relative w-full">
                            <div v-show="activeBreakdownTab === 'share'" class="h-full w-full">
                                <canvas ref="spendingShareCanvas"></canvas>
                            </div>
                            <div v-show="activeBreakdownTab === 'comparison'" class="h-full w-full">
                                <canvas ref="budgetComparisonCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </div>

                <!-- Right: Daily Budget & Surplus Equation Details (Span 4) -->
                <div class="lg:col-span-4 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                    <h3 class="text-xs font-black text-slate-855 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Pacing Calculation Details
                    </h3>
                        
                        <div class="space-y-4 text-[11px] text-slate-550 dark:text-slate-400">
                            <div class="space-y-1.5">
                                <h4 class="font-extrabold text-slate-700 dark:text-slate-300 uppercase text-[9px] tracking-wider">1. Daily Budget Share</h4>
                                <div class="font-mono bg-slate-50 dark:bg-slate-950 p-2 rounded-xl text-[9px] text-indigo-650 dark:text-indigo-400 font-extrabold">
                                    Daily Share = Sum(Daily Budgets) / Days
                                </div>
                                <p class="leading-relaxed text-slate-500">
                                    Baseline daily share allocation from active daily variable categories:
                                </p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span v-for="cat in daily_categories" :key="cat" class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-[9px] font-bold">
                                        {{ cat }}
                                    </span>
                                    <span v-if="daily_categories.length === 0" class="text-[9px] text-slate-400 italic">None</span>
                                </div>
                            </div>

                            <div class="space-y-1.5 border-t border-slate-100 dark:border-slate-800/80 pt-3">
                                <h4 class="font-extrabold text-slate-700 dark:text-slate-300 uppercase text-[9px] tracking-wider">2. Today's Budget (Dynamic Pacing)</h4>
                                <div class="font-mono bg-slate-50 dark:bg-slate-950 p-2 rounded-xl text-[9px] text-indigo-650 dark:text-indigo-400 font-extrabold">
                                    Today's Budget = Monthly Remaining / Remaining Days
                                </div>
                                <p class="leading-relaxed text-slate-500">
                                    Pacing daily allowance (including today). Excludes:
                                </p>
                                <div class="space-y-2 mt-2">
                                    <div>
                                        <span class="text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase block mb-1">Excluded Weekly Categories</span>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="cat in weekly_categories" :key="cat" class="px-1.5 py-0.5 rounded bg-purple-50 dark:bg-purple-950/40 text-purple-650 dark:text-purple-400 text-[9px] font-bold">
                                                {{ cat }}
                                            </span>
                                            <span v-if="weekly_categories.length === 0" class="text-[9px] text-slate-400 italic">None</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase block mb-1">Excluded Monthly Categories</span>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="cat in one_time_categories" :key="cat" class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-955/20 text-amber-600 dark:text-amber-400 text-[9px] font-bold">
                                                {{ cat }}
                                            </span>
                                            <span v-if="one_time_categories.length === 0" class="text-[9px] text-slate-400 italic">None</span>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category-wise Progress for Daily Expenses -->
           

            <!-- Daily Transactions Ledger Log -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black text-slate-855 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Daily Expenses Log ({{ transactions.length }})
                    </h3>
                </div>

                <div v-if="transactions.length === 0" class="py-16 text-center text-slate-500 dark:text-slate-655 italic font-semibold text-xs">
                    No expense transactions registered on this date.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-150 dark:border-slate-800 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="p-4">Description</th>
                                <th class="p-4">Category</th>
                                <th class="p-4">Account Source</th>
                                <th class="p-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs">
                            <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-50/30 dark:hover:bg-slate-850/10">
                                <td class="p-4 font-bold text-slate-805 dark:text-white">{{ t.description || 'No Description' }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-1.5">
                                        <span 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                            :style="{ 
                                                borderColor: `${t.category_color}25`, 
                                                backgroundColor: `${t.category_color}10`, 
                                                color: t.category_color 
                                            }"
                                        >
                                            {{ t.category_name }}
                                        </span>
                                        <span 
                                            v-if="t.expense_occurrence === 'one_time'"
                                            class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-955/20 text-amber-600 dark:text-amber-400 text-[9px] font-black uppercase tracking-wider border border-amber-200/20 pointer-events-auto cursor-help"
                                            title="Excluded from daily budget and surplus"
                                        >
                                            One-time
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-500 dark:text-slate-400 font-semibold">{{ t.account_name }}</td>
                                <td class="p-4 text-right font-black text-rose-600 dark:text-rose-400">
                                    -{{ formatCurrency(t.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
