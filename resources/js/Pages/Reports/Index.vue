<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    trends: {
        type: Array,
        required: true,
    },
    category_expenses: {
        type: Array,
        required: true,
    },
    accounts_report: {
        type: Array,
        required: true,
    },
    income_budget_report: {
        type: Array,
        required: true,
    },
    total_expense_budget: {
        type: Number,
        required: true,
    },
    total_savings_target: {
        type: Number,
        required: true,
    },
    averages: {
        type: Object,
        required: true,
    },
    projections: {
        type: Object,
        required: true,
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

// SVG Graph Calculations (Income vs Expense)
const maxAmount = computed(() => {
    const values = props.trends.flatMap(t => [t.income, t.expense]);
    const max = Math.max(...values, 100); // Default minimum max height limit
    return max * 1.15; // 15% padding at top
});

const chartWidth = 500;
const chartHeight = 220;
const paddingLeft = 50;
const paddingRight = 10;
const paddingTop = 20;
const paddingBottom = 30;

const drawWidth = chartWidth - paddingLeft - paddingRight;
const drawHeight = chartHeight - paddingTop - paddingBottom;

const barWidth = 14;
const gapBetweenMonths = drawWidth / props.trends.length;

const svgPoints = computed(() => {
    // Generate positions for the bars & y-axis lines
    return props.trends.map((t, index) => {
        const xCenter = paddingLeft + (index * gapBetweenMonths) + (gapBetweenMonths / 2);
        
        // Convert amounts to Y coordinates
        const incomeY = chartHeight - paddingBottom - (t.income / maxAmount.value * drawHeight);
        const expenseY = chartHeight - paddingBottom - (t.expense / maxAmount.value * drawHeight);
        const savingsY = chartHeight - paddingBottom - (t.savings / maxAmount.value * drawHeight);
        
        const incomeHeight = (t.income / maxAmount.value * drawHeight);
        const expenseHeight = (t.expense / maxAmount.value * drawHeight);

        return {
            label: t.label,
            incomeX: xCenter - barWidth - 2,
            incomeY,
            incomeHeight,
            expenseX: xCenter + 2,
            expenseY,
            expenseHeight,
            savingsX: xCenter,
            savingsY,
            savingsVal: t.savings,
        };
    });
});

// Y-Axis tick helper
const yAxisTicks = computed(() => {
    const ticks = [];
    const step = maxAmount.value / 4;
    for (let i = 0; i <= 4; i++) {
        const value = step * i;
        const y = chartHeight - paddingBottom - (value / maxAmount.value * drawHeight);
        ticks.push({ value, y });
    }
    return ticks;
});

// Savings Accumulation Line Points
const savingsLinePath = computed(() => {
    if (svgPoints.value.length === 0) return '';
    return svgPoints.value
        .map((p, index) => `${index === 0 ? 'M' : 'L'} ${p.savingsX} ${p.savingsY}`)
        .join(' ');
});

// Total category expenses
const totalCategoryExpenses = computed(() => {
    return props.category_expenses.reduce((sum, cat) => sum + cat.total, 0);
});

const totalIncomeTargetSummary = computed(() => {
    return props.income_budget_report.reduce((totals, cat) => {
        totals.limit += cat.limit || 0;
        totals.earned += cat.earned || 0;
        totals.deficit += cat.deficit || 0;
        return totals;
    }, { limit: 0, earned: 0, deficit: 0 });
});

// Financial Health logic
const healthStatus = computed(() => {
    const savings = props.averages.savings;
    if (props.averages.income === 0 && props.averages.expense === 0) {
        return {
            type: 'empty',
            title: 'No Data Collected Yet',
            message: 'Start logging your monthly incomes and expenses on the dashboard to see your custom reports and projections.',
            colorClass: 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-100 border-slate-200 dark:border-slate-750',
            badgeClass: 'bg-slate-200 dark:bg-slate-750 text-slate-800 dark:text-slate-200'
        };
    }
    if (savings > 0) {
        const rate = props.averages.savings_rate;
        if (rate >= 20) {
            return {
                type: 'excellent',
                title: 'Excellent Savings Rate!',
                message: `You are saving ${rate}% of your monthly income. This exceeds the recommended 20% benchmark. Your financial future is on a highly secure trajectory!`,
                colorClass: 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-250 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-450',
                badgeClass: 'bg-emerald-200 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300'
            };
        } else {
            return {
                type: 'good',
                title: 'Positive Saving Rate',
                message: `You are currently saving ${rate}% of your income (${formatCurrency(savings)}/month). Try adjusting category budget limits to reach the recommended 20% threshold.`,
                colorClass: 'bg-indigo-50 dark:bg-indigo-950/20 border-indigo-250 dark:border-indigo-900/40 text-indigo-800 dark:text-indigo-400',
                badgeClass: 'bg-indigo-200 dark:bg-indigo-900/50 text-indigo-800 dark:text-indigo-300'
            };
        }
    } else {
        return {
            type: 'deficit',
            title: 'Monthly Savings Deficit Alert',
            message: `Your average monthly expenses exceed your income by ${formatCurrency(Math.abs(savings))}. If this rate continues, you will draw down your overall savings. Review your expense transactions and categories immediately.`,
            colorClass: 'bg-rose-50 dark:bg-rose-950/25 border-rose-250 dark:border-rose-900/40 text-rose-800 dark:text-rose-405',
            badgeClass: 'bg-rose-200 dark:bg-rose-900/50 text-rose-800 dark:text-rose-300'
        };
    }
});

const totalAccountsSummary = computed(() => {
    let initial = 0;
    let inflows = 0;
    let outflows = 0;
    let netFlow = 0;
    let current = 0;
    
    props.accounts_report.forEach(acc => {
        initial += acc.initial_balance;
        inflows += acc.total_inflows;
        outflows += acc.total_outflows;
        netFlow += acc.net_flow;
        current += acc.current_balance;
    });
    
    return {
        initial,
        inflows,
        outflows,
        netFlow,
        current
    };
});
</script>

<template>
    <Head title="Financial Reports & Predictions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Reports & Predictions
                </h2>
                <div class="flex rounded-xl bg-slate-100 dark:bg-slate-900/80 p-1 self-start md:self-center border border-slate-200/55 dark:border-slate-800/60 shadow-sm">
                    <Link 
                        :href="route('reports.index')" 
                        class="px-4 py-2 text-xs font-semibold rounded-lg transition-all"
                        :class="route().current('reports.index') ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-600 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Financial Trends
                    </Link>
                    <Link 
                        :href="route('reports.forecast')" 
                        class="px-4 py-2 text-xs font-semibold rounded-lg transition-all"
                        :class="route().current('reports.forecast') ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-600 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Unified 12M Forecast
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- Financial Insight Header -->
                <div class="p-6 rounded-2xl border border-slate-100 dark:border-slate-800/60 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4" :class="healthStatus.colorClass">
                    <div class="space-y-1">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider mb-2" :class="healthStatus.badgeClass">
                            {{ healthStatus.type }}
                        </span>
                        <h3 class="text-2xl font-extrabold tracking-tight">{{ healthStatus.title }}</h3>
                        <p class="text-sm opacity-90 max-w-3xl leading-relaxed">
                            {{ healthStatus.message }}
                        </p>
                    </div>
                </div>

                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Avg Monthly Income</p>
                        <h4 class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(averages.income) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Last 3 months average</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Avg Monthly Expense</p>
                        <h4 class="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400">
                            {{ formatCurrency(averages.expense) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Last 3 months average</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Avg Monthly Savings</p>
                        <h4 class="text-2xl font-bold mt-1" :class="averages.savings >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-405'">
                            {{ averages.savings >= 0 ? '+' : '' }}{{ formatCurrency(averages.savings) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Net accumulation</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Avg Savings Rate</p>
                        <h4 class="text-2xl font-bold mt-1" :class="averages.savings_rate >= 0 ? 'text-indigo-650 dark:text-indigo-400' : 'text-rose-600 dark:text-rose-405'">
                            {{ averages.savings_rate }}%
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Recommended: 20%+</span>
                    </div>
                </div>

                <!-- Projections & Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Projections table -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Savings Predictions
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-700 mb-6 font-medium">
                                Wealth forecast if current monthly savings rate remains constant.
                            </p>

                            <div class="space-y-4">
                                <!-- 3 Months -->
                                <div class="flex items-center justify-between p-3.5 rounded-xl border border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40">
                                    <div>
                                        <p class="text-sm font-bold">3 Months Forecast</p>
                                        <p class="text-[10px] text-slate-600">Quarterly standing</p>
                                    </div>
                                    <p class="text-lg font-black" :class="projections.three_months >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-450'">
                                        {{ projections.three_months >= 0 ? '+' : '' }}{{ formatCurrency(projections.three_months) }}
                                    </p>
                                </div>

                                <!-- 6 Months -->
                                <div class="flex items-center justify-between p-3.5 rounded-xl border border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40">
                                    <div>
                                        <p class="text-sm font-bold">6 Months Forecast</p>
                                        <p class="text-[10px] text-slate-600">Half year projection</p>
                                    </div>
                                    <p class="text-lg font-black" :class="projections.six_months >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-450'">
                                        {{ projections.six_months >= 0 ? '+' : '' }}{{ formatCurrency(projections.six_months) }}
                                    </p>
                                </div>

                                <!-- 12 Months -->
                                <div class="flex items-center justify-between p-3.5 rounded-xl border border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40">
                                    <div>
                                        <p class="text-sm font-bold">12 Months Forecast</p>
                                        <p class="text-[10px] text-slate-600">Annual net worth increase</p>
                                    </div>
                                    <p class="text-lg font-black" :class="projections.twelve_months >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-450'">
                                        {{ projections.twelve_months >= 0 ? '+' : '' }}{{ formatCurrency(projections.twelve_months) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Micro Animation Forecast Indicator -->
                        <div class="mt-6 p-4 rounded-xl border border-indigo-100 dark:border-slate-800 bg-indigo-50/30 dark:bg-slate-950/50 text-xs">
                            <span class="font-bold text-slate-800 dark:text-slate-200">🔍 Insight: </span>
                            <span class="text-slate-600 dark:text-slate-600 leading-normal" v-if="projections.twelve_months > 0">
                                Saving regularly allows you to capture compound interest. Consider putting your projected annual savings of <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(projections.twelve_months) }}</span> in a High Yield Savings Account (HYSA).
                            </span>
                            <span class="text-slate-600 dark:text-slate-600 leading-normal" v-else>
                                A negative annual projection of <span class="font-bold text-rose-600 dark:text-rose-400">{{ formatCurrency(projections.twelve_months) }}</span> means your current expenses are unsustainable. Try reducing discretionary spending first.
                            </span>
                        </div>
                    </div>

                    <!-- Trend chart (Last 6 Months) -->
                    <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    Inflow & Outflow Trends
                                </h3>
                                <div class="flex items-center gap-3 text-xs font-bold">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 bg-emerald-500 rounded"></span>
                                        <span>Income</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 bg-rose-500 rounded"></span>
                                        <span>Expense</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3.5 h-0.5 bg-indigo-500 inline-block border-t border-indigo-500"></span>
                                        <span>Savings</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive SVG Chart -->
                            <div class="w-full overflow-hidden">
                                <svg 
                                    class="w-full h-auto text-slate-350 dark:text-slate-650"
                                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                                    fill="none" 
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <!-- Y-Axis Grid Lines & Ticks -->
                                    <g v-for="tick in yAxisTicks" :key="tick.value">
                                        <line 
                                            :x1="paddingLeft" 
                                            :y1="tick.y" 
                                            :x2="chartWidth - paddingRight" 
                                            :y2="tick.y" 
                                            stroke="currentColor" 
                                            stroke-dasharray="3 3" 
                                            stroke-opacity="0.25"
                                        />
                                        <text 
                                            :x="paddingLeft - 8" 
                                            :y="tick.y + 4" 
                                            fill="currentColor" 
                                            class="text-[10px] font-bold text-right text-slate-600"
                                            text-anchor="end"
                                        >
                                            {{ formatCurrency(tick.value).replace(/\.00$/, '') }}
                                        </text>
                                    </g>

                                    <!-- X-Axis Line -->
                                    <line 
                                        :x1="paddingLeft" 
                                        :y1="chartHeight - paddingBottom" 
                                        :x2="chartWidth - paddingRight" 
                                        :y2="chartHeight - paddingBottom" 
                                        stroke="currentColor" 
                                        stroke-opacity="0.5"
                                    />

                                    <!-- Render Monthly Bars & Savings Plot Circles -->
                                    <g v-for="pt in svgPoints" :key="pt.label">
                                        <!-- Income Bar -->
                                        <rect 
                                            v-if="pt.incomeHeight > 0"
                                            :x="pt.incomeX" 
                                            :y="pt.incomeY" 
                                            :width="barWidth" 
                                            :height="pt.incomeHeight" 
                                            rx="3" 
                                            class="fill-emerald-500 dark:fill-emerald-600"
                                        />
                                        
                                        <!-- Expense Bar -->
                                        <rect 
                                            v-if="pt.expenseHeight > 0"
                                            :x="pt.expenseX" 
                                            :y="pt.expenseY" 
                                            :width="barWidth" 
                                            :height="pt.expenseHeight" 
                                            rx="3" 
                                            class="fill-rose-500 dark:fill-rose-600"
                                        />

                                        <!-- Month Text Label -->
                                        <text 
                                            :x="pt.savingsX" 
                                            :y="chartHeight - paddingBottom + 16" 
                                            fill="currentColor" 
                                            class="text-[10px] font-bold text-slate-600"
                                            text-anchor="middle"
                                        >
                                            {{ pt.label }}
                                        </text>
                                    </g>

                                    <!-- Draw Net Savings Trend Line -->
                                    <path 
                                        :d="savingsLinePath" 
                                        fill="none" 
                                        class="stroke-indigo-500 dark:stroke-indigo-400" 
                                        stroke-width="2.5" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round"
                                    />

                                    <!-- Draw Savings Plots (Dots) -->
                                    <circle 
                                        v-for="pt in svgPoints" 
                                        :key="'dot-' + pt.label"
                                        :cx="pt.savingsX" 
                                        :cy="pt.savingsY" 
                                        r="4" 
                                        class="fill-white dark:fill-slate-800 stroke-indigo-500 dark:stroke-indigo-400" 
                                        stroke-width="2"
                                    />
                                </svg>
                            </div>
                        </div>

                        <div class="text-[10px] text-slate-600 dark:text-slate-700 italic mt-3 font-semibold text-center">
                            Note: Projections & averages are calculated over the last 3 months, while trends visualize a 6-month historical window.
                        </div>
                    </div>
                </div>

                <!-- Section: Account Flow & Balances Report -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z" />
                        </svg>
                        Account Performance & Balances Report
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-700 mb-6 font-medium">
                        All-time cash inflow, outflow, and net balance statement per account.
                    </p>

                    <div v-if="accounts_report.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                        No accounts registered. Create accounts and log transactions on the dashboard to generate this report.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                    <th class="py-3 px-4">Account</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4 text-right">Initial Balance</th>
                                    <th class="py-3 px-4 text-right">Total Inflows</th>
                                    <th class="py-3 px-4 text-right">Total Outflows</th>
                                    <th class="py-3 px-4 text-right">Net Flow</th>
                                    <th class="py-3 px-4 text-right">Current Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr 
                                    v-for="acc in accounts_report" 
                                    :key="acc.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span 
                                                class="w-3 h-3 rounded-full inline-block flex-shrink-0"
                                                :style="{ backgroundColor: acc.color }"
                                            ></span>
                                            {{ acc.name }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs font-medium text-slate-700 dark:text-slate-600 uppercase tracking-wider whitespace-nowrap">
                                        {{ acc.type.replace('_', ' ') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-slate-650 dark:text-slate-350 font-bold whitespace-nowrap">
                                        {{ formatCurrency(acc.initial_balance) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-emerald-600 dark:text-emerald-450 font-bold whitespace-nowrap">
                                        +{{ formatCurrency(acc.total_inflows) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-rose-600 dark:text-rose-450 font-bold whitespace-nowrap">
                                        -{{ formatCurrency(acc.total_outflows) }}
                                    </td>
                                    <td 
                                        class="py-3.5 px-4 text-right font-black whitespace-nowrap"
                                        :class="acc.net_flow >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-405'"
                                    >
                                        {{ acc.net_flow >= 0 ? '+' : '' }}{{ formatCurrency(acc.net_flow) }}
                                    </td>
                                    <td 
                                        class="py-3.5 px-4 text-right font-black whitespace-nowrap"
                                        :class="acc.current_balance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-405'"
                                    >
                                        {{ formatCurrency(acc.current_balance) }}
                                    </td>
                                </tr>
                                <!-- Totals row -->
                                <tr class="bg-slate-50/50 dark:bg-slate-950/30 font-black border-t-2 border-slate-200 dark:border-slate-800">
                                    <td colspan="2" class="py-4 px-4 text-slate-900 dark:text-white uppercase tracking-wider text-xs">Total Statement</td>
                                    <td class="py-4 px-4 text-right text-slate-700 dark:text-slate-100">{{ formatCurrency(totalAccountsSummary.initial) }}</td>
                                    <td class="py-4 px-4 text-right text-emerald-600 dark:text-emerald-400">+{{ formatCurrency(totalAccountsSummary.inflows) }}</td>
                                    <td class="py-4 px-4 text-right text-rose-600 dark:text-rose-400">-{{ formatCurrency(totalAccountsSummary.outflows) }}</td>
                                    <td 
                                        class="py-4 px-4 text-right"
                                        :class="totalAccountsSummary.netFlow >= 0 ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-405'"
                                    >
                                        {{ totalAccountsSummary.netFlow >= 0 ? '+' : '' }}{{ formatCurrency(totalAccountsSummary.netFlow) }}
                                    </td>
                                    <td 
                                        class="py-4 px-4 text-right text-base text-indigo-650 dark:text-indigo-400"
                                    >
                                        {{ formatCurrency(totalAccountsSummary.current) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section: Income Target & Deficit Report -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M12 14a2 2 0 110-4h4" />
                                </svg>
                                Monthly Income Target & Deficit Report
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-700 font-medium">
                                Target versus actual income earned and remaining deficits/surpluses for the current month.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <div class="bg-slate-50 dark:bg-slate-950 px-4 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800/80 text-right min-w-[140px]">
                                <span class="text-[9px] text-slate-600 dark:text-slate-600 font-bold uppercase tracking-wider block">Total Expense Budget</span>
                                <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">{{ formatCurrency(total_expense_budget) }}</span>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 px-4 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800/80 text-right min-w-[140px]">
                                <span class="text-[9px] text-slate-600 dark:text-slate-600 font-bold uppercase tracking-wider block">Savings Target (Current Month Only)</span>
                                <span class="text-sm font-extrabold" :class="total_savings_target >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-450'">
                                    {{ total_savings_target >= 0 ? '+' : '' }}{{ formatCurrency(total_savings_target) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="income_budget_report.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                        No monthly income targets configured. Set income targets on the dashboard to generate this report.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-405">
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4 text-right">Target Income</th>
                                    <th class="py-3 px-4 text-right">Actual Earned</th>
                                    <th class="py-3 px-4 text-right">Deficit Amount</th>
                                    <th class="py-3 px-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr 
                                    v-for="cat in income_budget_report" 
                                    :key="cat.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span 
                                                class="w-3.5 h-3.5 rounded-full inline-block flex-shrink-0"
                                                :style="{ backgroundColor: cat.color }"
                                            ></span>
                                            {{ cat.name }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-slate-800 dark:text-slate-200 font-bold whitespace-nowrap">
                                        {{ formatCurrency(cat.limit) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-slate-800 dark:text-slate-200 font-bold whitespace-nowrap">
                                        {{ formatCurrency(cat.earned) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-extrabold whitespace-nowrap" :class="cat.deficit > 0 ? 'text-rose-600 dark:text-rose-450' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ cat.deficit > 0 ? formatCurrency(cat.deficit) : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <span 
                                            class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                            :class="cat.status === 'Met' ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-450'"
                                        >
                                        </span>
                                    </td>
                                </tr>
                                <!-- Totals Row -->
                                <tr class="bg-slate-50/50 dark:bg-slate-900/50 font-black border-t-2 border-slate-200 dark:border-slate-800">
                                    <td class="py-4 px-4 text-slate-850 dark:text-slate-100 whitespace-nowrap">Total Target Summary</td>
                                    <td class="py-4 px-4 text-right text-slate-800 dark:text-slate-200 font-bold whitespace-nowrap">
                                        {{ formatCurrency(totalIncomeTargetSummary.limit) }}
                                    </td>
                                    <td class="py-4 px-4 text-right text-slate-800 dark:text-slate-200 font-bold whitespace-nowrap">
                                        {{ formatCurrency(totalIncomeTargetSummary.earned) }}
                                    </td>
                                    <td class="py-4 px-4 text-right font-extrabold whitespace-nowrap text-rose-600 dark:text-rose-450">
                                        {{ totalIncomeTargetSummary.deficit > 0 ? formatCurrency(totalIncomeTargetSummary.deficit) : '-' }}
                                    </td>
                                    <td class="py-4 px-4 text-right whitespace-nowrap">
                                        <span 
                                            class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                                            :class="totalIncomeTargetSummary.earned >= totalIncomeTargetSummary.limit ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-450'"
                                        >
                                            {{ totalIncomeTargetSummary.earned >= totalIncomeTargetSummary.limit ? 'Met' : 'Deficit' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Lower Section: Category Breakdown -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                    
                    <!-- Category Breakdown chart segment -->
                    <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            Outflow Breakdown (Last 6 Months)
                        </h3>

                        <div v-if="category_expenses.length === 0" class="py-12 text-center text-slate-600 dark:text-slate-700 italic font-semibold text-sm">
                            No expense data logged in categories over the last 6 months.
                        </div>

                        <div v-else class="space-y-6">
                            <!-- Visual Progress Bar Stack -->
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-5 overflow-hidden p-0.5 border border-slate-100 dark:border-slate-800 flex">
                                <div 
                                    v-for="cat in category_expenses" 
                                    :key="'bar-' + cat.id"
                                    class="h-full transition-all duration-300 first:rounded-l-full last:rounded-r-full"
                                    :style="{ 
                                        backgroundColor: cat.color, 
                                        width: `${(cat.total / totalCategoryExpenses) * 100}%` 
                                    }"
                                    :title="`${cat.name}: ${formatCurrency(cat.total)} (${roundPct(cat.total)}%)`"
                                ></div>
                            </div>

                            <!-- Legend List -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div 
                                    v-for="cat in category_expenses" 
                                    :key="'legend-' + cat.id"
                                    class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span 
                                            class="w-3.5 h-3.5 rounded-full inline-block flex-shrink-0"
                                            :style="{ backgroundColor: cat.color }"
                                        ></span>
                                        <div>
                                            <p class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ cat.name }}</p>
                                            <p class="text-[10px] text-slate-600 font-semibold">{{ roundPct(cat.total) }}% of total expense</p>
                                        </div>
                                    </div>
                                    <p class="font-extrabold text-sm text-slate-800 dark:text-slate-100">
                                        {{ formatCurrency(cat.total) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Health Alert Tips -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Recommended Actions
                            </h3>

                            <ul class="space-y-4 text-xs text-slate-600 dark:text-slate-600 leading-relaxed font-medium">
                                <li class="flex items-start gap-2.5">
                                    <span class="text-indigo-500 mt-0.5">▪</span>
                                    <span>
                                        <strong>Diversify categories</strong>: High concentration of outflow in single category indicates over-reliance. Aim for a balanced budget across essential categories.
                                    </span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="text-indigo-500 mt-0.5">▪</span>
                                    <span>
                                        <strong>50/30/20 Rule</strong>: Divide your income into 50% for Needs (Bills, Rent), 30% for Wants (Entertainment), and 20% for Savings or Debt Paydowns.
                                    </span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="text-indigo-500 mt-0.5">▪</span>
                                    <span>
                                        <strong>Build Emergency Buffer</strong>: Keep 3 to 6 months of average expenses (approx. <span class="font-bold text-slate-850 dark:text-slate-150">{{ formatCurrency(averages.expense * 3) }}</span>) in cash reserves.
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-4 text-[10px] text-slate-600 dark:text-slate-700 font-bold uppercase tracking-wider flex items-center justify-between">
                            <span>Target ratio: 20% savings</span>
                            <span>On Track</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script>
export default {
    methods: {
        roundPct(val) {
            if (this.totalCategoryExpenses === 0) return 0;
            return Math.round((val / this.totalCategoryExpenses) * 100);
        }
    }
}
</script>
