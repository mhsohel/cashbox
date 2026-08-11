<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    accounts: {
        type: Array,
        required: true,
    },
    starting_net_worth: {
        type: Number,
        required: true,
    },
    averages: {
        type: Object,
        required: true,
    },
    licenses: {
        type: Array,
        required: true,
    },
    loans: {
        type: Array,
        required: true,
    },
    budgets: {
        type: Array,
        required: true,
    },
    timeline_months: {
        type: Array,
        required: true,
    },
});

// Interactive state
const incomeChange = ref(0);
const expenseCutsPct = ref(0);
const includeSaaS = ref(true);
const includeLoans = ref(true);

const hoveredIndex = ref(null);

// Formatter helper
const formatCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

const isNonCompliant = (b) => {
    if (b.category_type === 'income') {
        return b.avg_spend < b.limit;
    }
    return b.avg_spend > b.limit;
};

const complianceStatus = (b) => {
    if (b.category_type === 'income') {
        return b.avg_spend < b.limit ? 'Deficit' : 'Target Met';
    }
    return b.avg_spend > b.limit ? 'Over Limit' : 'Compliant';
};

// Calculations for the next 12 months
const projections = computed(() => {
    let currentNW = props.starting_net_worth;
    const monthsData = [];

    props.timeline_months.forEach((m, idx) => {
        // Base Income & Expense
        const baseIncome = Math.max(0, props.averages.income + parseFloat(incomeChange.value || 0));
        const baseExpense = Math.max(0, props.averages.expense * (1 - parseFloat(expenseCutsPct.value || 0) / 100));

        // SaaS renewals in this month
        let monthlySaaSAmount = 0;
        const renewingSaaS = [];
        if (includeSaaS.value) {
            props.licenses.forEach(lic => {
                if (lic.billing_cycle === 'monthly') {
                    monthlySaaSAmount += lic.amount;
                    renewingSaaS.push(lic);
                } else if (lic.billing_cycle === 'yearly') {
                    if (lic.next_renewal_date) {
                        const renewalDate = new Date(lic.next_renewal_date);
                        const rMonth = renewalDate.getMonth() + 1;
                        if (rMonth === m.month) {
                            monthlySaaSAmount += lic.amount;
                            renewingSaaS.push(lic);
                        }
                    }
                }
            });
        }

        // Loan events in this month
        let monthlyLoanInflow = 0;
        let monthlyLoanOutflow = 0;
        const dueLoans = [];
        if (includeLoans.value) {
            props.loans.forEach(loan => {
                if (loan.due_date) {
                    const dueDate = new Date(loan.due_date);
                    const dYear = dueDate.getFullYear();
                    const dMonth = dueDate.getMonth() + 1;
                    if (dYear === m.year && dMonth === m.month) {
                        dueLoans.push(loan);
                        if (loan.type === 'lent') {
                            monthlyLoanInflow += loan.amount;
                        } else if (loan.type === 'borrowed') {
                            monthlyLoanOutflow += loan.amount;
                        }
                    }
                }
            });
        }

        const totalInflow = baseIncome + monthlyLoanInflow;
        const totalOutflow = baseExpense + monthlySaaSAmount + monthlyLoanOutflow;
        const netSavings = totalInflow - totalOutflow;
        currentNW += netSavings;

        monthsData.push({
            month_key: m.month_key,
            label: m.label,
            year: m.year,
            month: m.month,
            baseIncome,
            baseExpense,
            saasRenewals: renewingSaaS,
            saasAmount: monthlySaaSAmount,
            loanInflows: monthlyLoanInflow,
            loanOutflows: monthlyLoanOutflow,
            dueLoans,
            totalInflow,
            totalOutflow,
            netSavings,
            projectedNetWorth: currentNW,
        });
    });

    return monthsData;
});

// Chart parameters
const chartWidth = 600;
const chartHeight = 240;
const paddingLeft = 70;
const paddingRight = 20;
const paddingTop = 20;
const paddingBottom = 30;

const drawWidth = chartWidth - paddingLeft - paddingRight;
const drawHeight = chartHeight - paddingTop - paddingBottom;

// Net Worth Min/Max bounds for chart scaling
const maxNetWorth = computed(() => {
    const values = projections.value.map(p => p.projectedNetWorth);
    const max = Math.max(...values, props.starting_net_worth, 100);
    return max * 1.15; // 15% top padding
});

const minNetWorth = computed(() => {
    const values = projections.value.map(p => p.projectedNetWorth);
    const min = Math.min(...values, props.starting_net_worth, 0);
    return min < 0 ? min * 1.15 : 0; // 15% bottom padding if negative
});

const getY = (val) => {
    const range = maxNetWorth.value - minNetWorth.value;
    if (range === 0) return chartHeight - paddingBottom - drawHeight / 2;
    return chartHeight - paddingBottom - ((val - minNetWorth.value) / range * drawHeight);
};

const getX = (idx) => {
    return paddingLeft + (idx / 12) * drawWidth;
};

// SVG Paths
const linePath = computed(() => {
    let path = `M ${getX(0)} ${getY(props.starting_net_worth)}`;
    projections.value.forEach((p, idx) => {
        path += ` L ${getX(idx + 1)} ${getY(p.projectedNetWorth)}`;
    });
    return path;
});

const areaPath = computed(() => {
    const bottomY = getY(minNetWorth.value);
    let path = `M ${getX(0)} ${bottomY}`;
    path += ` L ${getX(0)} ${getY(props.starting_net_worth)}`;
    projections.value.forEach((p, idx) => {
        path += ` L ${getX(idx + 1)} ${getY(p.projectedNetWorth)}`;
    });
    path += ` L ${getX(12)} ${bottomY} Z`;
    return path;
});

// Y-Axis Ticks helper
const yAxisTicks = computed(() => {
    const ticks = [];
    const range = maxNetWorth.value - minNetWorth.value;
    const step = range / 4;
    for (let i = 0; i <= 4; i++) {
        const val = minNetWorth.value + step * i;
        ticks.push({
            value: val,
            y: getY(val),
        });
    }
    return ticks;
});

// Monthly inflow/outflow max for scaling
const maxMonthlyAmount = computed(() => {
    const values = projections.value.flatMap(p => [p.totalInflow, p.totalOutflow]);
    const max = Math.max(...values, 100);
    return max * 1.15;
});

const getBarY = (val) => {
    return chartHeight - paddingBottom - (val / maxMonthlyAmount.value * drawHeight);
};

const getBarHeight = (val) => {
    return (val / maxMonthlyAmount.value * drawHeight);
};

// Overview Cards Computed
const endingNetWorth = computed(() => {
    if (projections.value.length === 0) return props.starting_net_worth;
    return projections.value[11].projectedNetWorth;
});

const avgProjectedExpense = computed(() => {
    return projections.value.reduce((sum, p) => sum + p.totalOutflow, 0) / 12;
});

const emergencyRunwayMonths = computed(() => {
    const monthlyExp = avgProjectedExpense.value;
    if (monthlyExp <= 0) return 99; // effectively infinite
    const runway = props.starting_net_worth / monthlyExp;
    return Math.round(runway * 10) / 10;
});

const financialHealthScore = computed(() => {
    // Basic score calculation out of 100
    // Factors: savings rate, runway (6 months = 100%, 3 months = 50%), and over budget categories
    const avgSavings = projections.value.reduce((sum, p) => sum + p.netSavings, 0) / 12;
    const avgInflow = projections.value.reduce((sum, p) => sum + p.totalInflow, 0) / 12;
    const savingsRate = avgInflow > 0 ? (avgSavings / avgInflow) * 100 : 0;
    
    let score = 50; // base score
    
    // Savings rate factor (-20 to +25)
    if (savingsRate >= 20) score += 25;
    else if (savingsRate > 0) score += (savingsRate / 20) * 20;
    else score -= Math.min(20, Math.abs(savingsRate));

    // Runway factor (up to +25)
    const runway = emergencyRunwayMonths.value;
    if (runway >= 6) score += 25;
    else if (runway > 0) score += (runway / 6) * 25;

    // Over budget factor
    const nonCompliantBudgets = props.budgets.filter(b => {
        if (b.category_type === 'income') {
            return b.avg_spend < b.limit;
        }
        return b.avg_spend > b.limit;
    }).length;
    score -= nonCompliantBudgets * 5;

    return Math.max(0, Math.min(100, Math.round(score)));
});

const healthScoreColor = computed(() => {
    const score = financialHealthScore.value;
    if (score >= 80) return 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/20';
    if (score >= 50) return 'text-amber-500 bg-amber-50 dark:bg-amber-950/20';
    return 'text-rose-500 bg-rose-50 dark:bg-rose-950/20';
});
</script>

<template>
    <Head title="Unified Financial Forecast & Predictions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Consolidated Forecast
                    </h2>
                    <p class="text-sm text-slate-700 dark:text-slate-600 mt-1">
                        Predictive cash flow & asset projection combining SaaS contracts, outstanding loans, and budgets.
                    </p>
                </div>
                
                <!-- Sibling Tab Navigation -->
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
                
                <!-- Main Layout Grid: Sidebar Controls on Left, Charts/Dash on Right -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <!-- Left Sidebar: Controls & Parameters (Span 4) -->
                    <div class="lg:col-span-4 space-y-6">
                        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm space-y-6 sticky top-6">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                    Simulation Parameters
                                </h3>
                                <p class="text-[11px] text-slate-600 mt-1 font-medium leading-relaxed">
                                    Adjust standard values below to recalculate all 12-month projections instantly.
                                </p>
                            </div>

                            <!-- Slider: Income Growth -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700">Income Growth</label>
                                    <span class="text-xs font-black text-indigo-650 dark:text-indigo-400">
                                        {{ incomeChange >= 0 ? '+' : '' }}{{ formatCurrency(incomeChange) }} /mo
                                    </span>
                                </div>
                                <input 
                                    type="range" 
                                    min="-1000" 
                                    max="3000" 
                                    step="100" 
                                    v-model="incomeChange"
                                    class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                                />
                                <div class="flex justify-between text-[9px] text-slate-600 font-semibold">
                                    <span>-$1,000</span>
                                    <span>Baseline (Avg)</span>
                                    <span>+$3,000</span>
                                </div>
                            </div>

                            <!-- Slider: Spending Cuts -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700">Discretionary Cuts</label>
                                    <span class="text-xs font-black text-rose-600 dark:text-rose-400">
                                        -{{ expenseCutsPct }}% spending
                                    </span>
                                </div>
                                <input 
                                    type="range" 
                                    min="0" 
                                    max="50" 
                                    step="5" 
                                    v-model="expenseCutsPct"
                                    class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                                />
                                <div class="flex justify-between text-[9px] text-slate-600 font-semibold">
                                    <span>No Cuts (0%)</span>
                                    <span>25% Cut</span>
                                    <span>50% Max</span>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Toggles -->
                            <div class="space-y-4">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 block">Forecast Inclusions</label>
                                
                                <!-- Toggle: SaaS -->
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">SaaS Subscriptions</span>
                                        <span class="text-[10px] text-slate-600">Include renewals/contracts</span>
                                    </div>
                                    <div class="relative">
                                        <input type="checkbox" v-model="includeSaaS" class="sr-only peer" />
                                        <div class="w-9 h-5 bg-slate-200 dark:bg-slate-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-650"></div>
                                    </div>
                                </label>

                                <!-- Toggle: Loans -->
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Loans & Debts</span>
                                        <span class="text-[10px] text-slate-600">Include repayments by due date</span>
                                    </div>
                                    <div class="relative">
                                        <input type="checkbox" v-model="includeLoans" class="sr-only peer" />
                                        <div class="w-9 h-5 bg-slate-200 dark:bg-slate-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-650"></div>
                                    </div>
                                </label>
                            </div>

                            <!-- Financial Score Card -->
                            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800/60 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/30">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-600">Runway Health Score</p>
                                    <p class="text-[10px] text-slate-600 leading-normal mt-0.5 max-w-[160px]">Overall safety rating based on cash buffer & savings.</p>
                                </div>
                                <div class="w-14 h-14 rounded-full flex flex-col items-center justify-center font-extrabold text-lg shadow-sm border border-slate-100 dark:border-slate-750" :class="healthScoreColor">
                                    <span>{{ financialHealthScore }}</span>
                                    <span class="text-[8px] tracking-tight uppercase opacity-80 -mt-1">/100</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Stats & Charts (Span 8) -->
                    <div class="lg:col-span-8 space-y-6">
                        
                        <!-- Core Forecast Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                                <p class="text-slate-600 dark:text-slate-600 text-[10px] font-bold uppercase tracking-wider">Current Liquid Bal.</p>
                                <h4 class="text-xl font-extrabold mt-1 text-slate-900 dark:text-white">
                                    {{ formatCurrency(starting_net_worth) }}
                                </h4>
                                <span class="text-[9px] text-slate-600 dark:text-slate-700 font-semibold">Starting Net Worth</span>
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                                <p class="text-slate-600 dark:text-slate-600 text-[10px] font-bold uppercase tracking-wider">Projected NW (1 Yr)</p>
                                <h4 class="text-xl font-extrabold mt-1" :class="endingNetWorth >= starting_net_worth ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-450'">
                                    {{ formatCurrency(endingNetWorth) }}
                                </h4>
                                <span class="text-[9px] text-slate-600 dark:text-slate-700 font-semibold">
                                    {{ endingNetWorth >= starting_net_worth ? 'Growth:' : 'Deficit:' }} 
                                    {{ formatCurrency(Math.abs(endingNetWorth - starting_net_worth)) }}
                                </span>
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                                <p class="text-slate-600 dark:text-slate-600 text-[10px] font-bold uppercase tracking-wider">Avg Projected Outflow</p>
                                <h4 class="text-xl font-extrabold mt-1 text-rose-600 dark:text-rose-400">
                                    {{ formatCurrency(avgProjectedExpense) }}
                                </h4>
                                <span class="text-[9px] text-slate-600 dark:text-slate-700 font-semibold">Includes SaaS & loan costs</span>
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                                <p class="text-slate-600 dark:text-slate-600 text-[10px] font-bold uppercase tracking-wider">Emergency Runway</p>
                                <h4 class="text-xl font-extrabold mt-1 text-indigo-650 dark:text-indigo-400">
                                    {{ emergencyRunwayMonths }} Mo
                                </h4>
                                <span class="text-[9px] text-slate-600 dark:text-slate-700 font-semibold">
                                    {{ emergencyRunwayMonths >= 6 ? 'Excellent Buffer' : 'Buffer too low (< 6M)' }}
                                </span>
                            </div>
                        </div>

                        <!-- 12-Month Net Worth Projection Area Chart -->
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    12-Month Cumulative Net Worth Projection
                                </h3>
                                <span class="text-[10px] bg-indigo-50 dark:bg-slate-700 text-indigo-650 dark:text-indigo-300 font-extrabold uppercase px-2.5 py-0.5 rounded-full">
                                    Liquid + Claims - Debts
                                </span>
                            </div>

                            <!-- Net Worth Area Chart -->
                            <div class="relative w-full overflow-hidden">
                                <svg 
                                    class="w-full h-auto text-slate-200 dark:text-slate-700"
                                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                                    fill="none" 
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <!-- Gradients definition -->
                                    <defs>
                                        <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25" />
                                            <stop offset="100%" stop-color="#6366f1" stop-opacity="0.00" />
                                        </linearGradient>
                                    </defs>

                                    <!-- Grid Lines -->
                                    <g v-for="tick in yAxisTicks" :key="tick.value">
                                        <line 
                                            :x1="paddingLeft" 
                                            :y1="tick.y" 
                                            :x2="chartWidth - paddingRight" 
                                            :y2="tick.y" 
                                            stroke="currentColor" 
                                            stroke-dasharray="3 3" 
                                            stroke-opacity="0.4"
                                        />
                                        <text 
                                            :x="paddingLeft - 8" 
                                            :y="tick.y + 4" 
                                            fill="currentColor" 
                                            class="text-[9px] font-bold text-slate-600"
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

                                    <!-- Filled Area Path -->
                                    <path 
                                        :d="areaPath" 
                                        fill="url(#areaGradient)" 
                                    />

                                    <!-- Trend line -->
                                    <path 
                                        :d="linePath" 
                                        fill="none" 
                                        class="stroke-indigo-500 dark:stroke-indigo-400" 
                                        stroke-width="2.5" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round"
                                    />

                                    <!-- Dots on line -->
                                    <circle 
                                        :cx="getX(0)"
                                        :cy="getY(starting_net_worth)"
                                        r="3.5"
                                        class="fill-white dark:fill-slate-800 stroke-indigo-500 dark:stroke-indigo-400"
                                        stroke-width="1.5"
                                    />
                                    <circle 
                                        v-for="(p, idx) in projections" 
                                        :key="'pt-' + idx"
                                        :cx="getX(idx + 1)" 
                                        :cy="getY(p.projectedNetWorth)" 
                                        r="3.5" 
                                        class="fill-white dark:fill-slate-800 stroke-indigo-500 dark:stroke-indigo-400" 
                                        stroke-width="1.5"
                                    />

                                    <!-- X-Axis Labels (Months) -->
                                    <text 
                                        :x="getX(0)" 
                                        :y="chartHeight - paddingBottom + 14" 
                                        fill="currentColor" 
                                        class="text-[8px] font-bold text-slate-600"
                                        text-anchor="middle"
                                    >
                                        Current
                                    </text>
                                    <text 
                                        v-for="(p, idx) in projections" 
                                        :key="'lbl-' + idx"
                                        :x="getX(idx + 1)" 
                                        :y="chartHeight - paddingBottom + 14" 
                                        fill="currentColor" 
                                        class="text-[8px] font-bold text-slate-600"
                                        text-anchor="middle"
                                    >
                                        {{ p.label.split(' ')[0] }}
                                    </text>

                                    <!-- Hover Vertical Tracker Indicator -->
                                    <line 
                                        v-if="hoveredIndex !== null"
                                        :x1="getX(hoveredIndex)" 
                                        :y1="paddingTop" 
                                        :x2="getX(hoveredIndex)" 
                                        :y2="chartHeight - paddingBottom" 
                                        class="stroke-indigo-500/50 dark:stroke-indigo-400/50"
                                        stroke-width="1"
                                        stroke-dasharray="2 2"
                                    />

                                    <!-- Highlight hover dot -->
                                    <circle 
                                        v-if="hoveredIndex !== null"
                                        :cx="getX(hoveredIndex)"
                                        :cy="getY(hoveredIndex === 0 ? starting_net_worth : projections[hoveredIndex - 1].projectedNetWorth)"
                                        r="5.5"
                                        class="fill-indigo-500 dark:fill-indigo-400 stroke-white dark:stroke-slate-800"
                                        stroke-width="2"
                                    />

                                    <!-- Transparent Hover Zones -->
                                    <rect
                                        v-for="i in 13"
                                        :key="'zone-' + i"
                                        :x="getX(i - 1) - (drawWidth / 24)"
                                        :y="paddingTop"
                                        :width="drawWidth / 12"
                                        :height="drawHeight"
                                        fill="transparent"
                                        class="cursor-pointer"
                                        @mouseover="hoveredIndex = i - 1"
                                        @mouseleave="hoveredIndex = null"
                                    />
                                </svg>

                                <!-- Custom Tooltip Overlay -->
                                <div 
                                    v-if="hoveredIndex !== null" 
                                    class="absolute top-2 right-2 bg-slate-900/90 dark:bg-slate-950/95 backdrop-blur-md border border-slate-750 p-3.5 rounded-xl text-white text-[11px] shadow-lg max-w-[210px] space-y-1.5 transition-all duration-150"
                                >
                                    <div class="font-black text-xs text-indigo-300">
                                        {{ hoveredIndex === 0 ? 'Current Baseline' : projections[hoveredIndex - 1].label }}
                                    </div>
                                    <div class="flex justify-between gap-4 font-bold border-b border-slate-700/60 pb-1">
                                        <span>Net Worth:</span>
                                        <span class="text-indigo-250 font-black">
                                            {{ formatCurrency(hoveredIndex === 0 ? starting_net_worth : projections[hoveredIndex - 1].projectedNetWorth) }}
                                        </span>
                                    </div>
                                    <div v-if="hoveredIndex > 0" class="space-y-1 pt-0.5">
                                        <div class="flex justify-between text-slate-350">
                                            <span>Base Inflows:</span>
                                            <span class="text-emerald-400 font-extrabold">+{{ formatCurrency(projections[hoveredIndex - 1].baseIncome) }}</span>
                                        </div>
                                        <div class="flex justify-between text-slate-350" v-if="projections[hoveredIndex - 1].loanInflows > 0">
                                            <span>Loans Collected:</span>
                                            <span class="text-emerald-400 font-extrabold">+{{ formatCurrency(projections[hoveredIndex - 1].loanInflows) }}</span>
                                        </div>
                                        <div class="flex justify-between text-slate-350">
                                            <span>Base Outflows:</span>
                                            <span class="text-rose-400 font-extrabold">-{{ formatCurrency(projections[hoveredIndex - 1].baseExpense) }}</span>
                                        </div>
                                        <div class="flex justify-between text-slate-350" v-if="projections[hoveredIndex - 1].saasAmount > 0">
                                            <span>SaaS Renewals:</span>
                                            <span class="text-rose-400 font-extrabold">-{{ formatCurrency(projections[hoveredIndex - 1].saasAmount) }}</span>
                                        </div>
                                        <div class="flex justify-between text-slate-350" v-if="projections[hoveredIndex - 1].loanOutflows > 0">
                                            <span>Loans Repaid:</span>
                                            <span class="text-rose-400 font-extrabold">-{{ formatCurrency(projections[hoveredIndex - 1].loanOutflows) }}</span>
                                        </div>
                                        <div class="flex justify-between font-black text-xs pt-1 border-t border-slate-700/40">
                                            <span>Net Flow:</span>
                                            <span :class="projections[hoveredIndex - 1].netSavings >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                                {{ projections[hoveredIndex - 1].netSavings >= 0 ? '+' : '' }}{{ formatCurrency(projections[hoveredIndex - 1].netSavings) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Inflow & Outflow Bars -->
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Predicted Monthly Inflows vs Outflows
                            </h3>
                            
                            <div class="relative w-full overflow-hidden">
                                <svg 
                                    class="w-full h-auto text-slate-200 dark:text-slate-700"
                                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                                    fill="none" 
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <!-- Y Axis grid lines -->
                                    <g v-for="tick in [0, 0.25, 0.5, 0.75, 1]" :key="tick">
                                        <line 
                                            :x1="paddingLeft" 
                                            :y1="paddingTop + (1 - tick) * drawHeight" 
                                            :x2="chartWidth - paddingRight" 
                                            :y2="paddingTop + (1 - tick) * drawHeight" 
                                            stroke="currentColor" 
                                            stroke-dasharray="3 3" 
                                            stroke-opacity="0.4"
                                        />
                                        <text 
                                            :x="paddingLeft - 8" 
                                            :y="paddingTop + (1 - tick) * drawHeight + 3" 
                                            fill="currentColor" 
                                            class="text-[9px] font-bold text-slate-600"
                                            text-anchor="end"
                                        >
                                            {{ formatCurrency(maxMonthlyAmount * tick).replace(/\.00$/, '') }}
                                        </text>
                                    </g>

                                    <!-- Bars -->
                                    <g v-for="(p, idx) in projections" :key="'bar-' + idx">
                                        <!-- Inflow Bar (Green) -->
                                        <rect 
                                            v-if="p.totalInflow > 0"
                                            :x="paddingLeft + (idx * (drawWidth / 12)) + (drawWidth / 48)"
                                            :y="getBarY(p.totalInflow)"
                                            :width="drawWidth / 30"
                                            :height="getBarHeight(p.totalInflow)"
                                            rx="2"
                                            class="fill-emerald-500 dark:fill-emerald-600"
                                        />
                                        <!-- Outflow Bar (Red) -->
                                        <rect 
                                            v-if="p.totalOutflow > 0"
                                            :x="paddingLeft + (idx * (drawWidth / 12)) + (drawWidth / 48) + (drawWidth / 28)"
                                            :y="getBarY(p.totalOutflow)"
                                            :width="drawWidth / 30"
                                            :height="getBarHeight(p.totalOutflow)"
                                            rx="2"
                                            class="fill-rose-500 dark:fill-rose-600"
                                        />

                                        <!-- Month label -->
                                        <text 
                                            :x="paddingLeft + (idx * (drawWidth / 12)) + (drawWidth / 24)" 
                                            :y="chartHeight - paddingBottom + 14" 
                                            fill="currentColor" 
                                            class="text-[8px] font-bold text-slate-600"
                                            text-anchor="middle"
                                        >
                                            {{ p.label.split(' ')[0] }}
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
                                </svg>
                            </div>
                        </div>

                        <!-- Budgets & Category Safety warnings -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Category Budget Health -->
                            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 space-y-4">
                                <div>
                                    <h3 class="text-md font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Budget Risk Indicators
                                    </h3>
                                    <p class="text-[10px] text-slate-600 mt-0.5 leading-normal">
                                        Identifies categories where historical average spending exceeds budget limits.
                                    </p>
                                </div>

                                <div v-if="budgets.length === 0" class="text-xs text-slate-450 italic py-6 text-center">
                                    No budgets set. Log budgets on the dashboard to review category compliance.
                                </div>

                                <div v-else class="space-y-4 max-h-[250px] overflow-y-auto pr-1">
                                    <div 
                                        v-for="b in budgets" 
                                        :key="b.category_id"
                                        class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800/60 flex flex-col gap-2 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-all"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-xs font-bold">
                                                <span class="w-2.5 h-2.5 rounded-full inline-block" :style="{ backgroundColor: b.color }"></span>
                                                {{ b.category_name }}
                                            </span>
                                            <span 
                                                class="px-2 py-0.5 rounded-md text-[9px] font-extrabold uppercase"
                                                :class="isNonCompliant(b) ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400'"
                                            >
                                                {{ complianceStatus(b) }}
                                            </span>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="space-y-1">
                                            <div class="w-full bg-slate-200 dark:bg-slate-750 h-2 rounded-full overflow-hidden">
                                                <div 
                                                    class="h-full rounded-full transition-all"
                                                    :style="{ 
                                                        width: `${Math.min(100, (b.avg_spend / b.limit) * 100)}%`,
                                                        backgroundColor: isNonCompliant(b) ? '#F43F5E' : '#10B981'
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="flex justify-between text-[9px] font-semibold text-slate-600">
                                                <span>{{ b.category_type === 'income' ? 'Avg Earned' : 'Avg Spent' }}: {{ formatCurrency(b.avg_spend) }}/mo</span>
                                                <span>{{ b.category_type === 'income' ? 'Target' : 'Limit' }}: {{ formatCurrency(b.limit) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upcoming Timelines -->
                            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 space-y-4">
                                <div>
                                    <h3 class="text-md font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Outflow Timeline (Next 12M)
                                    </h3>
                                    <p class="text-[10px] text-slate-600 mt-0.5 leading-normal">
                                        Timeline view of scheduled contracts and outstanding debts.
                                    </p>
                                </div>

                                <div class="space-y-3 overflow-y-auto max-h-[250px] pr-1">
                                    <!-- Iterate next 12 months with specific events -->
                                    <template v-for="p in projections" :key="p.month_key">
                                        <div 
                                            v-if="p.saasRenewals.length > 0 || p.dueLoans.length > 0"
                                            class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20"
                                        >
                                            <div class="text-[10px] font-black text-indigo-650 dark:text-indigo-400 uppercase tracking-wider mb-2">
                                                {{ p.label }}
                                            </div>

                                            <div class="space-y-1.5">
                                                <!-- SaaS renewals -->
                                                <div 
                                                    v-for="saas in p.saasRenewals" 
                                                    :key="'ren-' + saas.id"
                                                    class="flex items-center justify-between text-xs text-slate-650 dark:text-slate-350"
                                                >
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="w-1.5 h-1.5 rounded bg-indigo-500 inline-block"></span>
                                                        SaaS: {{ saas.client_name }}
                                                    </span>
                                                    <span class="font-extrabold text-rose-500">-{{ formatCurrency(saas.amount) }}</span>
                                                </div>

                                                <!-- Loan repayments -->
                                                <div 
                                                    v-for="loan in p.dueLoans" 
                                                    :key="'ln-' + loan.id"
                                                    class="flex items-center justify-between text-xs font-bold"
                                                >
                                                    <span class="flex items-center gap-1.5" :class="loan.type === 'lent' ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-450'">
                                                        <span class="w-1.5 h-1.5 rounded inline-block" :class="loan.type === 'lent' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                                        {{ loan.type === 'lent' ? 'Claim:' : 'Debt:' }} {{ loan.person_name }}
                                                    </span>
                                                    <span :class="loan.type === 'lent' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'">
                                                        {{ loan.type === 'lent' ? '+' : '-' }}{{ formatCurrency(loan.amount) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* High performance slider adjustments */
input[type="range"]::-webkit-slider-thumb {
    transition: transform 0.1s ease-in-out;
}
input[type="range"]:active::-webkit-slider-thumb {
    transform: scale(1.25);
}
</style>
