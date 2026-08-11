<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => []
    },
    days_passed: {
        type: Number,
        default: 1
    },
    total_days: {
        type: Number,
        default: 30
    },
    has_mismatches: {
        type: Boolean,
        default: false
    },
    ai_global_advice: {
        type: String,
        default: ''
    },
    current_month: {
        type: String,
        default: ''
    }
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

// Filtered Lists
const expenseCategories = computed(() => {
    return props.categories.filter(c => c.type === 'expense');
});

const incomeCategories = computed(() => {
    return props.categories.filter(c => c.type === 'income');
});

// Totals and KPI stats
const totalBudgetLimit = computed(() => {
    return expenseCategories.value.reduce((sum, c) => sum + c.budget_limit, 0);
});

const totalSpent = computed(() => {
    return expenseCategories.value.reduce((sum, c) => sum + c.actual_amount, 0);
});

const totalIncomeTarget = computed(() => {
    return incomeCategories.value.reduce((sum, c) => sum + c.budget_limit, 0);
});

const totalEarned = computed(() => {
    return incomeCategories.value.reduce((sum, c) => sum + c.actual_amount, 0);
});

const monthProgressPercentage = computed(() => {
    return (props.days_passed / props.total_days) * 100;
});

// Helper for pacing calculation in template
const getBudgetUsedPercentage = (cat) => {
    if (!cat.budget_limit) return 0;
    return (cat.actual_amount / cat.budget_limit) * 100;
};
</script>

<template>
    <Head title="AI Budget Monitor" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        AI Budget Pacing Monitor
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Automated multi-category trend analyzer comparing actual pace vs ideal budget allocation.
                    </p>
                </div>
                
                <!-- Current Month / Day Badge -->
                <div class="flex items-center gap-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl px-4 py-2 shadow-sm">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ current_month }}
                    </span>
                    <span class="h-4 w-px bg-slate-200 dark:bg-slate-750"></span>
                    <span class="text-xs font-black text-indigo-650 dark:text-indigo-400">
                        Day {{ days_passed }} of {{ total_days }}
                    </span>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
            
            <!-- 1. AI Insights card (glassmorphism/gradient) -->
            <div 
                v-if="ai_global_advice"
                class="relative overflow-hidden rounded-3xl border border-indigo-100 dark:border-indigo-900/60 shadow-xl shadow-indigo-150/10 dark:shadow-none bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-700 p-6 md:p-8 text-white transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl hover:shadow-indigo-500/20"
            >
                <!-- Decorative absolute background circles -->
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center gap-6">
                    <!-- Glow-in-the-dark Sparkle Icon -->
                    <div class="flex-shrink-0 p-3.5 bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 shadow-inner flex items-center justify-center animate-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] uppercase font-black tracking-widest bg-white/20 px-2 py-1 rounded-full backdrop-blur-sm border border-white/10">
                                AI Copilot Insight
                            </span>
                            <span v-if="has_mismatches" class="text-[10px] uppercase font-black tracking-widest bg-rose-500/80 px-2 py-1 rounded-full">
                                Action Needed
                            </span>
                            <span v-else class="text-[10px] uppercase font-black tracking-widest bg-emerald-500/80 px-2 py-1 rounded-full">
                                Optimized
                            </span>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold leading-snug">
                            Strategic Reallocation Advice
                        </h3>
                        <p class="text-sm md:text-base text-indigo-50/90 leading-relaxed font-medium">
                            {{ ai_global_advice }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. High-level Month and Overall Pace Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Card 1: Month Pacing progress -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Month Progress</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-slate-850 dark:text-slate-100">
                            {{ monthProgressPercentage.toFixed(0) }}% Elapsed
                        </h4>
                        <p class="text-xs font-semibold text-slate-500 mt-1">
                            Day {{ days_passed }} out of {{ total_days }} days
                        </p>
                    </div>
                    <!-- Month progress bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full bg-gradient-to-r from-indigo-500 to-indigo-650 rounded-full"
                            :style="{ width: monthProgressPercentage + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Card 2: Overall Expenses vs Budget -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Expense Pacing</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-slate-850 dark:text-slate-100">
                            {{ formatCurrency(totalSpent) }}
                        </h4>
                        <p class="text-xs font-semibold text-slate-500 mt-1">
                            Budget limit: {{ formatCurrency(totalBudgetLimit) }}
                        </p>
                    </div>
                    
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all"
                            :class="totalSpent > totalBudgetLimit ? 'bg-rose-500' : (totalSpent / totalBudgetLimit * 100 > monthProgressPercentage ? 'bg-amber-500' : 'bg-emerald-500')"
                            :style="{ width: Math.min((totalBudgetLimit > 0 ? (totalSpent / totalBudgetLimit * 100) : 0), 100) + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Card 3: Overall System Health -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pacing Status</span>
                        <span 
                            class="w-2.5 h-2.5 rounded-full"
                            :class="has_mismatches ? 'bg-rose-500 animate-ping' : 'bg-emerald-500'"
                        ></span>
                    </div>
                    <div>
                        <h4 
                            class="text-2xl font-black uppercase tracking-wide"
                            :class="has_mismatches ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ has_mismatches ? 'Warning' : 'Balanced' }}
                        </h4>
                        <p class="text-xs font-semibold text-slate-500 mt-1">
                            {{ has_mismatches ? 'Some categories are pacing too fast!' : 'All categories pacing healthy!' }}
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-1.5 text-xs font-extrabold">
                        <span class="text-slate-400">Month End Projection:</span>
                        <span :class="totalSpent > totalBudgetLimit ? 'text-rose-500' : 'text-emerald-500'">
                            {{ totalSpent > totalBudgetLimit ? 'Deficit Track' : 'Saving Track' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 3. Expenses Pacing Section -->
            <div class="space-y-6">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="h-6 w-1 bg-indigo-500 rounded"></div>
                    <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider">
                        Expense Categories Pacing
                    </h3>
                </div>

                <div v-if="expenseCategories.length === 0" class="py-12 text-center text-slate-500 italic font-semibold text-xs bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
                    No budgeted expense categories registered for this month.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div 
                        v-for="cat in expenseCategories" 
                        :key="cat.id"
                        class="p-6 rounded-3xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col justify-between hover:shadow-md hover:border-slate-200 dark:hover:border-slate-750 transition-all duration-250"
                    >
                        <!-- Category Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-900 shadow-sm shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                <h4 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm tracking-tight">{{ cat.name }}</h4>
                            </div>
                            
                            <!-- Pacing Badges -->
                            <div v-if="cat.budget_limit > 0">
                                <span 
                                    v-if="cat.is_mismatched" 
                                    class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/30 flex items-center gap-1"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v12a1 1 0 00.293.707L6 20v-4l8 4V0L6 4H3.707z" clip-rule="evenodd" />
                                    </svg>
                                    Over Pacing
                                </span>
                                <span 
                                    v-else 
                                    class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-1"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6.267 3.585a.75.75 0 011.05 0l6 6a.75.75 0 010 1.05l-6 6a.75.75 0 11-1.05-1.05L11.69 10 5.217 3.535a.75.75 0 010-1.05z" clip-rule="evenodd" />
                                    </svg>
                                    On Track
                                </span>
                            </div>
                            <span v-else class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-400">
                                No Target
                            </span>
                        </div>

                        <!-- Progress Bar with Marker -->
                        <div v-if="cat.budget_limit > 0" class="space-y-4">
                            
                            <!-- Pacing Comparison Graph -->
                            <div class="relative pt-6 pb-2">
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 relative">
                                    <!-- Budget used progress -->
                                    <div 
                                        class="h-full rounded-full transition-all duration-500" 
                                        :class="cat.is_mismatched ? 'bg-gradient-to-r from-rose-400 to-rose-500 shadow-sm' : 'bg-gradient-to-r from-emerald-400 to-emerald-500 shadow-sm'"
                                        :style="{ width: Math.min(getBudgetUsedPercentage(cat), 100) + '%' }"
                                    ></div>

                                    <!-- Day Pace Marker -->
                                    <div 
                                        class="absolute top-[-8px] bottom-[-8px] w-[3px] bg-slate-500 dark:bg-slate-400 z-10"
                                        :style="{ left: monthProgressPercentage + '%' }"
                                    >
                                        <!-- Little Marker Flag -->
                                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 flex flex-col items-center">
                                            <span class="text-[8px] bg-slate-800 dark:bg-slate-700 text-white font-extrabold px-1.5 py-0.5 rounded shadow">
                                                Day {{ days_passed }}
                                            </span>
                                            <div class="w-0.5 h-1 bg-slate-800 dark:bg-slate-700"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pacing Statistics -->
                            <div class="grid grid-cols-2 gap-4 bg-slate-50/50 dark:bg-slate-800/20 p-3 rounded-2xl border border-slate-100/50 dark:border-slate-800/40 text-xs">
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Ideal Spending Rate</p>
                                    <p class="font-extrabold text-slate-700 dark:text-slate-300">
                                        {{ formatCurrency(cat.ideal_pace_per_day) }}<span class="text-[10px] text-slate-400 font-semibold">/day</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Actual Spending Rate</p>
                                    <p 
                                        class="font-black"
                                        :class="cat.is_mismatched ? 'text-rose-500' : 'text-emerald-500'"
                                    >
                                        {{ formatCurrency(cat.actual_pace_per_day) }}<span class="text-[10px] text-slate-400 font-semibold">/day</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Total Spent / Limit</p>
                                    <p class="font-bold text-slate-655 dark:text-slate-400">
                                        {{ formatCurrency(cat.actual_amount) }} <span class="text-[9px] font-semibold text-slate-450">of {{ formatCurrency(cat.budget_limit) }}</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Pacing Surplus</p>
                                    <p 
                                        class="font-black"
                                        :class="cat.pace_surplus >= 0 ? 'text-emerald-500' : 'text-rose-500'"
                                    >
                                        {{ cat.pace_surplus >= 0 ? '+' : '' }}{{ formatCurrency(cat.pace_surplus) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Bottom stats footer -->
                            <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-1">
                                <span>{{ getBudgetUsedPercentage(cat).toFixed(0) }}% Budget Used</span>
                                <span :class="cat.actual_amount > cat.budget_limit ? 'text-rose-500 font-extrabold' : 'text-emerald-500'">
                                    {{ cat.actual_amount > cat.budget_limit ? 'Over budget by ' + formatCurrency(cat.actual_amount - cat.budget_limit) : formatCurrency(cat.budget_limit - cat.actual_amount) + ' left' }}
                                </span>
                            </div>

                        </div>
                        <div v-else class="text-xs text-slate-500 dark:text-slate-600 italic py-4 bg-slate-50/50 dark:bg-slate-800/10 rounded-2xl text-center border border-dashed border-slate-200 dark:border-slate-800">
                            No limit configured. Total Spent: <span class="font-bold text-slate-700 dark:text-slate-300">{{ formatCurrency(cat.actual_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Income Targets Pacing Section -->
            <div class="space-y-6">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="h-6 w-1 bg-emerald-500 rounded"></div>
                    <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider">
                        Income Targets Pacing
                    </h3>
                </div>

                <div v-if="incomeCategories.length === 0" class="py-12 text-center text-slate-500 italic font-semibold text-xs bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
                    No budgeted income categories registered for this month.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div 
                        v-for="cat in incomeCategories" 
                        :key="cat.id"
                        class="p-6 rounded-3xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col justify-between hover:shadow-md hover:border-slate-200 dark:hover:border-slate-750 transition-all duration-250"
                    >
                        <!-- Category Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-900 shadow-sm shrink-0" :style="{ backgroundColor: cat.color }"></span>
                                <h4 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm tracking-tight">{{ cat.name }}</h4>
                            </div>
                            
                            <!-- Pacing Badges -->
                            <div v-if="cat.budget_limit > 0">
                                <span 
                                    v-if="cat.is_mismatched" 
                                    class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/30 flex items-center gap-1"
                                    title="Earning slower than the ideal daily target pace"
                                >
                                    Behind Pace
                                </span>
                                <span 
                                    v-else 
                                    class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-1"
                                >
                                    Pacing Well
                                </span>
                            </div>
                            <span v-else class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-400">
                                No Target
                            </span>
                        </div>

                        <!-- Progress Bar with Marker -->
                        <div v-if="cat.budget_limit > 0" class="space-y-4">
                            
                            <!-- Pacing Comparison Graph -->
                            <div class="relative pt-6 pb-2">
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 relative">
                                    <!-- Income achieved progress -->
                                    <div 
                                        class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-emerald-450 to-emerald-500 shadow-sm"
                                        :style="{ width: Math.min(getBudgetUsedPercentage(cat), 100) + '%' }"
                                    ></div>

                                    <!-- Day Pace Marker -->
                                    <div 
                                        class="absolute top-[-8px] bottom-[-8px] w-[3px] bg-slate-500 dark:bg-slate-400 z-10"
                                        :style="{ left: monthProgressPercentage + '%' }"
                                    >
                                        <!-- Little Marker Flag -->
                                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 flex flex-col items-center">
                                            <span class="text-[8px] bg-slate-800 dark:bg-slate-700 text-white font-extrabold px-1.5 py-0.5 rounded shadow">
                                                Day {{ days_passed }}
                                            </span>
                                            <div class="w-0.5 h-1 bg-slate-800 dark:bg-slate-700"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pacing Statistics -->
                            <div class="grid grid-cols-2 gap-4 bg-slate-50/50 dark:bg-slate-800/20 p-3 rounded-2xl border border-slate-100/50 dark:border-slate-800/40 text-xs">
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Target Earning Rate</p>
                                    <p class="font-extrabold text-slate-700 dark:text-slate-300">
                                        {{ formatCurrency(cat.ideal_pace_per_day) }}<span class="text-[10px] text-slate-400 font-semibold">/day</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Actual Earning Rate</p>
                                    <p 
                                        class="font-black"
                                        :class="cat.is_mismatched ? 'text-rose-500' : 'text-emerald-500'"
                                    >
                                        {{ formatCurrency(cat.actual_pace_per_day) }}<span class="text-[10px] text-slate-400 font-semibold">/day</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Total Earned / Target</p>
                                    <p class="font-bold text-slate-655 dark:text-slate-400">
                                        {{ formatCurrency(cat.actual_amount) }} <span class="text-[9px] font-semibold text-slate-450">of {{ formatCurrency(cat.budget_limit) }}</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[9px] uppercase font-black tracking-widest mb-1">Target Surplus</p>
                                    <p 
                                        class="font-black"
                                        :class="cat.pace_surplus >= 0 ? 'text-emerald-500' : 'text-rose-500'"
                                    >
                                        {{ cat.pace_surplus >= 0 ? '+' : '' }}{{ formatCurrency(cat.pace_surplus) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Bottom stats footer -->
                            <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-1">
                                <span>{{ getBudgetUsedPercentage(cat).toFixed(0) }}% Achieved</span>
                                <span :class="cat.actual_amount >= cat.budget_limit ? 'text-emerald-500 font-extrabold' : 'text-slate-500'">
                                    {{ cat.actual_amount >= cat.budget_limit ? 'Target Met!' : formatCurrency(cat.budget_limit - cat.actual_amount) + ' left to earn' }}
                                </span>
                            </div>

                        </div>
                        <div v-else class="text-xs text-slate-500 dark:text-slate-600 italic py-4 bg-slate-50/50 dark:bg-slate-800/10 rounded-2xl text-center border border-dashed border-slate-200 dark:border-slate-800">
                            No target configured. Total Earned: <span class="font-bold text-slate-700 dark:text-slate-350">{{ formatCurrency(cat.actual_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
