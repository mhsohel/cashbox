<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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

            <!-- Comparison Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Daily Expense Budget Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-5 text-indigo-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Daily Expense Budget</span>
                        <h3 class="text-2xl font-black text-slate-850 dark:text-white mt-2 tracking-tight">
                            {{ formatCurrency(budget.expense) }}
                        </h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed font-semibold mt-3">
                            Apportioned share from monthly perpetual expense budget envelopes.
                        </p>
                    </div>
                </div>

                <!-- Actual Daily Expense Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-5 text-rose-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Actual Expense Spent</span>
                        <h3 class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-2 tracking-tight">
                            {{ formatCurrency(actual.expense) }}
                        </h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed font-semibold mt-3">
                            Sum of all expense transactions recorded on this specific date.
                        </p>
                    </div>
                </div>

                <!-- Cumulative Monthly Savings Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-5" :class="cumulative_savings >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Cumulative Monthly Savings</span>
                        <h3 
                            class="text-2xl font-black mt-2 tracking-tight"
                            :class="cumulative_savings >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455'"
                        >
                            {{ formatCurrency(cumulative_savings) }}
                        </h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed font-semibold mt-3">
                            Cumulative unspent budget (Cumulative budget allocation minus cumulative actual expenses in this month so far).
                        </p>
                    </div>
                </div>

            </div>

            <!-- Diagnostics Gauge Bar Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                <h3 class="text-sm font-black text-slate-850 dark:text-slate-200 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Daily Spending Pacing Audit
                </h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center text-xs font-bold text-slate-600 dark:text-slate-400">
                        <span>Actual Spending ({{ formatCurrency(actual.expense) }}) vs Daily Apportioned Budget ({{ formatCurrency(budget.expense) }})</span>
                        <span 
                            class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                            :class="actual.expense > budget.expense ? 'bg-rose-100 text-rose-800 dark:bg-rose-955/40 dark:text-rose-400' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-955/40 dark:text-emerald-400'"
                        >
                            {{ actual.expense > budget.expense ? 'Over Spend' : 'Within Budget' }}
                        </span>
                    </div>

                    <div class="h-4 w-full bg-slate-100 dark:bg-slate-950 rounded-full overflow-hidden relative border border-slate-200 dark:border-slate-850">
                        <div 
                            class="h-full transition-all duration-300 rounded-full"
                            :class="[
                                actual.expense > budget.expense ? 'bg-gradient-to-r from-rose-500 to-red-600' : 'bg-gradient-to-r from-emerald-500 to-teal-500',
                            ]"
                            :style="{ width: `${budget.expense > 0 ? Math.min(100, (actual.expense / budget.expense) * 100) : 0}%` }"
                        ></div>
                    </div>
                    
                    <p class="text-[11px] text-slate-500 leading-relaxed font-semibold">
                        * Keeping actual daily expenditures below your apportioned budget target ensures your monthly cash reserves remain compliant.
                    </p>
                </div>
            </div>

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
