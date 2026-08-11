<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

// Interactive Simulator State
const activeTab = ref('ledger'); // 'ledger' | 'saas' | 'accounts' | 'loans' | 'analytics' | 'ai_monitor'
const simAcmePaid = ref(false);
const simAcmeRenewal = ref('2026-07-29');
const simAiReallocated = ref(false);
const simToast = ref(null);


// Formatter helper for Simulator
const formatSimCurrency = (value) => {
    const val = parseFloat(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return (val < 0 ? '-' : '') + '৳' + formatted;
};

const simTransactions = ref([
    { id: 1, type: 'income', desc: 'SaaS License Payment - SpaceX', amount: 25000, date: 'Today' },
    { id: 2, type: 'expense', desc: 'AWS Cloud Hosting', amount: 1500, date: 'Yesterday' },
    { id: 3, type: 'expense', desc: 'Office Groceries & Coffee', amount: 450, date: '2 days ago' },
]);

const simBudgets = ref([
    { name: 'Food & Dining', spent: 3500, limit: 5500, color: '#10B981', percentage: 63 },
    { name: 'Entertainment', spent: 800, limit: 1000, color: '#F59E0B', percentage: 80 },
    { name: 'SaaS Hosting', spent: 2200, limit: 2000, color: '#EF4444', percentage: 110 },
]);

const simAccounts = ref([
    { id: 1, name: 'Bank Savings', balance: 120000.00, type: 'bank' },
    { id: 2, name: 'bKash Wallet', balance: 15000.00, type: 'mobile_wallet' },
]);
const simTransferDone = ref(false);
const simRepaymentDone = ref(false);

const simLoans = ref([
    { id: 1, person: 'John Doe', type: 'lent', amount: 5000, outstanding: 5000, due: '2026-07-10', status: 'active' },
    { id: 2, person: 'Jane Smith', type: 'borrowed', amount: 3000, outstanding: 3000, due: '2026-07-20', status: 'active' },
]);

const simMRR = ref(28500);
const simARR = computed(() => simMRR.value * 12);

const triggerToast = (msg) => {
    simToast.value = msg;
    setTimeout(() => {
        if (simToast.value === msg) {
            simToast.value = null;
        }
    }, 4000);
};

const simulatePay = () => {
    if (simAcmePaid.value) {
        triggerToast("Acme Corp payment has already been logged for this period!");
        return;
    }
    simAcmePaid.value = true;
    simAcmeRenewal.value = '2026-08-29';
    simMRR.value += 3500;
    
    // Add transaction to the ledger log
    simTransactions.value.unshift({
        id: Date.now(),
        type: 'income',
        desc: 'SaaS License Payment - Acme Corp',
        amount: 3500,
        date: 'Just Now'
    });
    
    triggerToast("💸 Payment logged! +৳3,500 income added and next renewal date advanced.");
};

const simulateReallocation = () => {
    if (simAiReallocated.value) {
        triggerToast("AI pacing recommendation has already been applied!");
        return;
    }
    simAiReallocated.value = true;
    triggerToast("🧠 AI recommendation executed! Limits adjusted: Food limit increased, Entertainment limit decreased.");
};

const simulateTransfer = () => {

    if (simTransferDone.value) {
        triggerToast("Transfer has already been simulated! Reset the demo to try again.");
        return;
    }
    simTransferDone.value = true;
    
    // Update balances
    simAccounts.value[0].balance -= 3000;
    simAccounts.value[1].balance += 3000;
    
    // Add transfer logs to the ledger list
    simTransactions.value.unshift({
        id: Date.now(),
        type: 'income',
        desc: 'Fund Transfer (To bKash Wallet)',
        amount: 3000,
        date: 'Just Now',
        is_transfer: true,
    });
    simTransactions.value.unshift({
        id: Date.now() + 1,
        type: 'expense',
        desc: 'Fund Transfer (From Bank Savings)',
        amount: 3000,
        date: 'Just Now',
        is_transfer: true,
    });
    
    triggerToast("💳 Fund Transfer logged! -৳3,000 from Bank Savings, +৳3,000 to bKash Wallet.");
};

const simulateRepayment = () => {
    if (simRepaymentDone.value) {
        triggerToast("Repayment has already been simulated! Reset the demo to try again.");
        return;
    }
    simRepaymentDone.value = true;
    
    // John Doe repayment of ৳1,500
    simLoans.value[0].outstanding -= 1500;
    simAccounts.value[0].balance += 1500; // Bank savings increases
    
    simTransactions.value.unshift({
        id: Date.now(),
        type: 'income',
        desc: 'Loan Repayment - John Doe',
        amount: 1500,
        date: 'Just Now'
    });
    
    triggerToast("🤝 Repayment logged! +৳1,500 received from John Doe, outstanding reduced.");
};

const resetSimulator = () => {
    simAcmePaid.value = false;
    simAcmeRenewal.value = '2026-07-29';
    simMRR.value = 28500;
    simAccounts.value = [
        { id: 1, name: 'Bank Savings', balance: 120000.00, type: 'bank' },
        { id: 2, name: 'bKash Wallet', balance: 15000.00, type: 'mobile_wallet' },
    ];
    simTransferDone.value = false;
    simRepaymentDone.value = false;
    simLoans.value = [
        { id: 1, person: 'John Doe', type: 'lent', amount: 5000, outstanding: 5000, due: '2026-07-10', status: 'active' },
        { id: 2, person: 'Jane Smith', type: 'borrowed', amount: 3000, outstanding: 3000, due: '2026-07-20', status: 'active' },
    ];
    simTransactions.value = [
        { id: 1, type: 'income', desc: 'SaaS License Payment - SpaceX', amount: 25000, date: 'Today' },
        { id: 2, type: 'expense', desc: 'AWS Cloud Hosting', amount: 1500, date: 'Yesterday' },
        { id: 3, type: 'expense', desc: 'Office Groceries & Coffee', amount: 450, date: '2 days ago' },
    ];
    triggerToast("🔄 Simulator reset to initial state.");
};

// Interactive FAQs State
const faqs = ref([
    {
        q: "Can I self-host Cashbox for free?",
        a: "Absolutely! The core product is fully open-source and self-hostable. It runs seamlessly on PHP 8.2+ and SQLite, requiring zero complex configuration.",
        open: false
    },
    {
        q: "How does the SaaS License tracking sync with the ledger?",
        a: "Whenever you log a subscription payment on the SaaS License Manager page, it automatically generates a corresponding 'Income' transaction linked to the designated asset account and pushes the client's next renewal date forward by one billing cycle (monthly or yearly).",
        open: false
    },
    {
        q: "Are the financial analytics calculated dynamically?",
        a: "Yes! All metrics, category budget progress bars, receivable/payable balances, and trend projections are calculated on-the-fly directly from your ledger transactions. The system calculates a rolling 3-month savings rate to predict your 3, 6, and 12-month balances.",
        open: false
    },
    {
        q: "Where is my financial data stored?",
        a: "In the self-hosted version, all records are stored directly inside your local SQLite database file, giving you complete data sovereignty. In the Cloud version, your database is encrypted at rest and hosted in a high-security container.",
        open: false
    }
]);

const toggleFaq = (index) => {
    faqs.value[index].open = !faqs.value[index].open;
};

// Premium Service Request Form State & Logic
const serviceForm = useForm({
    name: '',
    email: '',
    phone: '',
    service_type: 'custom_feature',
    budget: '500_1500',
    description: '',
});

const showServiceSuccess = ref(false);

const submitServiceOrder = () => {
    serviceForm.post(route('premium-service-orders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showServiceSuccess.value = true;
            serviceForm.reset();
            setTimeout(() => {
                showServiceSuccess.value = false;
            }, 6000);
        },
    });
};
</script>

<template>
    <Head title="CashBox - Premium Multi-Account Budget, Loans & SaaS Revenue OS" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white overflow-x-hidden relative">
        
        <!-- Decorative Glowing Elements -->
        <div class="absolute top-0 left-1/4 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
        <div class="absolute top-1/4 right-1/4 w-[600px] h-[600px] bg-violet-600/10 rounded-full blur-[140px] -z-10 pointer-events-none"></div>
        <div class="absolute bottom-10 left-10 w-[400px] h-[400px] bg-emerald-500/5 rounded-full blur-[100px] -z-10 pointer-events-none"></div>

        <!-- Sticky Header / Navbar -->
        <header class="border-b border-slate-800/80 bg-slate-955/80 backdrop-blur-md sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand Logo -->
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center shadow-lg shadow-indigo-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <defs>
                                <linearGradient id="welVaultGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#818CF8" />
                                    <stop offset="50%" stop-color="#C084FC" />
                                    <stop offset="100%" stop-color="#FB7185" />
                                </linearGradient>
                                <linearGradient id="welDialGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#34D399" />
                                    <stop offset="100%" stop-color="#60A5FA" />
                                </linearGradient>
                            </defs>
                            <rect x="3" y="4" width="18" height="16" rx="3.5" stroke="url(#welVaultGrad)" stroke-width="2.5" />
                            <circle cx="12" cy="12" r="3.8" stroke="url(#welDialGrad)" stroke-width="1.8" />
                            <circle cx="12" cy="12" r="1.2" fill="url(#welVaultGrad)" />
                            <path d="M12 6v1M12 17v1M6 12h1M17 12h1" stroke="url(#welDialGrad)" stroke-width="1.2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <span class="text-xl font-black bg-gradient-to-r from-white via-slate-100 to-indigo-400 bg-clip-text text-transparent tracking-tight">CashBox</span>
                </div>

                <!-- Navigation Action Links -->
                <nav v-if="canLogin" class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all duration-150 active:scale-95"
                    >
                        Go to Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-semibold text-slate-400 hover:text-white transition"
                        >
                            Sign In
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all duration-150 active:scale-95"
                        >
                            Get Started Free
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 text-center lg:text-left lg:grid lg:grid-cols-12 lg:gap-12 items-center">
            
            <!-- Hero Left: Sales Copy -->
            <div class="lg:col-span-5 space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/35 text-indigo-455 text-xs font-bold uppercase tracking-wider animate-pulse">
                    <span>⚡ SQLite Driven Fast Core</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-450"></span>
                    <span>৳ Bangladeshi Taka Default</span>
                </div>
                
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white leading-tight">
                    The Smart Multi-Account Budget, Debt & SaaS Billing OS<br/>
                    <span class="bg-gradient-to-r from-indigo-400 via-violet-400 to-emerald-400 bg-clip-text text-transparent">
                        For Indie Hackers & Creators.
                    </span>
                </h1>
                
                <p class="text-base sm:text-lg text-slate-400 font-medium leading-relaxed max-w-xl mx-auto lg:mx-0">
                    Consolidate cash flows across bank accounts, credit cards, and mobile wallets (bKash/Nagad). Track daily budgets, log outstanding loans, process recurring bill reminders, and manage client SaaS licensing revenues.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-4">
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="px-8 py-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/20 transition-all duration-155 active:scale-95"
                    >
                        Create Your Account
                    </Link>
                    <Link
                        v-else-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-8 py-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/20 transition-all duration-155 active:scale-95"
                    >
                        Open Dashboard
                    </Link>
                    <a
                        href="#interactive-demo"
                        class="px-8 py-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-800 font-bold text-sm transition"
                    >
                        Try Live Simulator
                    </a>
                </div>

                <div class="flex items-center justify-center lg:justify-start gap-6 pt-6 text-slate-500 text-xs font-bold">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4.5 w-4.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        No CC Required
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4.5 w-4.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        Self-Hostable Core
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4.5 w-4.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        Double-Entry
                    </div>
                </div>
            </div>

            <!-- Hero Right: Interactive Dashboard Widget -->
            <div class="lg:col-span-7 mt-12 lg:mt-0" id="interactive-demo">
                
                <!-- Mock Browser Panel Container -->
                <div class="relative bg-slate-900/60 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden backdrop-blur-sm">
                    
                    <!-- Browser Chrome Header -->
                    <div class="bg-slate-950 px-4 py-3 border-b border-slate-850 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="ml-4 text-xs font-bold text-slate-500 tracking-wider">CASHBOX LIVE PLATFORM SIMULATOR</span>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="resetSimulator" 
                                class="px-2.5 py-1 bg-slate-850 hover:bg-slate-800 text-[10px] font-bold text-indigo-400 rounded border border-slate-750 transition flex items-center gap-1"
                                title="Reset Simulator"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89H18" />
                                </svg>
                                Reset Demo
                            </button>
                        </div>
                    </div>

                    <!-- Simulator Tabs -->
                    <div class="flex overflow-x-auto border-b border-slate-850 bg-slate-950/40 text-xs font-bold text-slate-500 whitespace-nowrap scrollbar-none">
                        <button 
                            @click="activeTab = 'ledger'"
                            :class="activeTab === 'ledger' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            📊 Ledger & Budgets
                        </button>
                        <button 
                            @click="activeTab = 'accounts'"
                            :class="activeTab === 'accounts' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            💳 Transfers & Accounts
                        </button>
                        <button 
                            @click="activeTab = 'loans'"
                            :class="activeTab === 'loans' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            🤝 Debt Tracker
                        </button>
                        
                        <button 
                            @click="activeTab = 'analytics'"
                            :class="activeTab === 'analytics' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            📈 Savings Forecast
                        </button>
                        <button 
                            @click="activeTab = 'ai_monitor'"
                            :class="activeTab === 'ai_monitor' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            🧠 AI Budget Monitor
                        </button>
                        <button 
                            @click="activeTab = 'saas'"
                            :class="activeTab === 'saas' ? 'text-indigo-405 border-b-2 border-indigo-500 bg-slate-900/40' : 'hover:text-white'"
                            class="px-5 py-3.5 transition"
                        >
                            💼 SaaS Subscriptions
                        </button>
                    </div>


                    <!-- Simulator Body -->
                    <div class="p-6 min-h-[380px] flex flex-col justify-between">
                        
                        <!-- TAB 1: Ledger & Budgets -->
                        <div v-if="activeTab === 'ledger'" class="space-y-6">
                            <!-- Simulated Budgets -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Interactive Category Budgets (Warnings & Alarms)</h4>
                                <div v-for="bg in simBudgets" :key="bg.name" class="space-y-1">
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-slate-300">{{ bg.name }}</span>
                                        <span class="text-slate-500">{{ formatSimCurrency(bg.spent) }} / {{ formatSimCurrency(bg.limit) }} ({{ bg.percentage }}%)</span>
                                    </div>
                                    <div class="w-full bg-slate-955 rounded-full h-2 overflow-hidden border border-slate-800">
                                        <div 
                                            class="h-full rounded-full transition-all duration-500" 
                                            :style="{ width: Math.min(bg.percentage, 100) + '%', backgroundColor: bg.color }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ledger Transactions List -->
                            <div class="space-y-2.5">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Simulated Daily Ledger</h4>
                                <div class="space-y-2 max-h-[135px] overflow-y-auto">
                                    <div 
                                        v-for="tx in simTransactions" 
                                        :key="tx.id"
                                        class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/40 border border-slate-850 text-xs hover:bg-slate-950 transition duration-150"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span 
                                                class="w-2.5 h-2.5 rounded-full shrink-0"
                                                :class="tx.type === 'income' ? 'bg-emerald-500' : 'bg-rose-500'"
                                            ></span>
                                            <span class="font-bold text-slate-200 truncate max-w-[190px] sm:max-w-xs">{{ tx.desc }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-slate-600 text-[10px]">{{ tx.date }}</span>
                                            <span 
                                                class="font-extrabold"
                                                :class="tx.type === 'income' ? 'text-emerald-400' : 'text-rose-500'"
                                            >
                                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatSimCurrency(tx.amount) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: Multi-Account Transfers -->
                        <div v-if="activeTab === 'accounts'" class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Multi-Account Double-Entry Flow</h4>
                                <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                    Cashbox features double-entry transfers. Try transferring money from your savings account to your mobile wallet.
                                </p>

                                <div class="grid grid-cols-2 gap-4">
                                    <div v-for="acc in simAccounts" :key="acc.id" class="p-4 rounded-2xl bg-slate-950/50 border border-slate-800 space-y-1">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase">{{ acc.type === 'bank' ? 'Bank Account' : 'Mobile Wallet' }}</span>
                                        <h5 class="text-sm font-bold text-white">{{ acc.name }}</h5>
                                        <p class="text-lg font-black text-indigo-400">{{ formatSimCurrency(acc.balance) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-indigo-950/20 border border-indigo-900/50 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div class="text-left space-y-0.5">
                                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wide">Simulator Action</span>
                                    <p class="text-xs font-bold text-slate-350">Transfer ৳3,000 to bKash Wallet</p>
                                </div>
                                <button 
                                    @click="simulateTransfer" 
                                    :disabled="simTransferDone"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs transition disabled:opacity-40"
                                >
                                    {{ simTransferDone ? 'Transferred ✓' : 'Execute Transfer' }}
                                </button>
                            </div>
                        </div>

                        <!-- TAB 3: Debt Tracker -->
                        <div v-if="activeTab === 'loans'" class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Loans & Outstanding Debt Statements</h4>
                                <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                    Log lent or borrowed cash. Manage outstanding amounts, schedule due dates, and update statement logs upon repayment.
                                </p>

                                <div class="space-y-3">
                                    <div v-for="loan in simLoans" :key="loan.id" class="p-3.5 rounded-xl bg-slate-950/40 border border-slate-800 flex items-center justify-between text-xs">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-white">{{ loan.person }}</span>
                                                <span 
                                                    class="px-2 py-0.5 rounded text-[9px] font-bold uppercase"
                                                    :class="loan.type === 'lent' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
                                                >
                                                    {{ loan.type === 'lent' ? 'Lent (Receivable)' : 'Borrowed (Payable)' }}
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-slate-500 mt-1">Due: {{ loan.due }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] text-slate-500 block">Outstanding</span>
                                            <span class="font-black text-slate-200">{{ formatSimCurrency(loan.outstanding) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-indigo-950/20 border border-indigo-900/50 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div class="text-left space-y-0.5">
                                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wide">Simulator Action</span>
                                    <p class="text-xs font-bold text-slate-350">Collect ৳1,500 repayment from John Doe</p>
                                </div>
                                <button 
                                    @click="simulateRepayment" 
                                    :disabled="simRepaymentDone"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs transition disabled:opacity-40"
                                >
                                    {{ simRepaymentDone ? 'Logged ✓' : 'Log Repayment' }}
                                </button>
                            </div>
                        </div>

                        <!-- TAB 4: SaaS Subscriptions -->
                        <div v-if="activeTab === 'saas'" class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">SaaS License & Subscription Revenue Manager</h4>
                                <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                    Track MRR & ARR. Record recurring license collections, advance client renewal schedules, and push ledger entries automatically.
                                </p>

                                <div class="p-4 rounded-2xl bg-slate-950/50 border border-slate-800 flex items-center justify-between gap-4">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase">Acme Corporation License</span>
                                        <h5 class="text-sm font-bold text-white mt-0.5">Plan: Standard Enterprise</h5>
                                        <p class="text-xs text-slate-400 mt-1">Next Renewal: <span class="text-indigo-400 font-extrabold">{{ simAcmeRenewal }}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-black text-white block">৳3,500.00 / mo</span>
                                        <span 
                                            class="px-2 py-0.5 rounded text-[8px] font-black uppercase mt-1.5 inline-block"
                                            :class="simAcmePaid ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
                                        >
                                            {{ simAcmePaid ? 'Paid' : 'Unpaid' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-indigo-950/20 border border-indigo-900/50 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div class="text-left space-y-0.5">
                                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wide">Simulator Action</span>
                                    <p class="text-xs font-bold text-slate-350">Collect subscription payment of ৳3,500</p>
                                </div>
                                <button 
                                    @click="simulatePay" 
                                    :disabled="simAcmePaid"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs transition disabled:opacity-40"
                                >
                                    {{ simAcmePaid ? 'Logged ✓' : 'Process Payment' }}
                                </button>
                            </div>
                        </div>

                        <!-- TAB 5: Savings Projections -->
                        <div v-if="activeTab === 'analytics'" class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Savings Forecast Engine</h4>
                                <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                    Predict future assets based on rolling monthly performance. The platform dynamically models 3, 6, and 12-month balances.
                                </p>

                                <div class="grid grid-cols-3 gap-3">
                                    <div class="p-3.5 rounded-xl bg-slate-950/50 border border-slate-800 text-center space-y-1.5">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase block">3-Month Forecast</span>
                                        <p class="text-xs font-bold text-slate-400">৳205,500</p>
                                        <span class="text-[8px] font-bold text-emerald-450 uppercase">+৳85.5K Net</span>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-slate-950/50 border border-slate-800 text-center space-y-1.5">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase block">6-Month Forecast</span>
                                        <p class="text-xs font-bold text-slate-400">৳291,000</p>
                                        <span class="text-[8px] font-bold text-emerald-450 uppercase">+৳171K Net</span>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-slate-950/50 border border-slate-800 text-center space-y-1.5">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase block">12-Month Forecast</span>
                                        <p class="text-xs font-bold text-slate-400">৳462,000</p>
                                        <span class="text-[8px] font-bold text-emerald-450 uppercase">+৳342K Net</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-indigo-950/10 border border-indigo-900/30 text-xs text-slate-400 leading-relaxed flex items-center gap-3">
                                <span class="text-lg shrink-0">📈</span>
                                <p class="font-semibold">
                                    Calculated with a rolling monthly average income of <strong>৳28,500</strong> and savings rate of <strong>৳28,500</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- TAB 6: AI Budget Monitor -->
                        <div v-if="activeTab === 'ai_monitor'" class="space-y-6">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Real-Time Pacing Analyzer (Day 10 of 31)</h4>
                                    <span class="text-[9px] bg-rose-500/20 text-rose-400 font-extrabold px-1.5 py-0.5 rounded border border-rose-500/30 uppercase tracking-widest animate-pulse">Pacing Deficit</span>
                                </div>
                                <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                    Cashbox calculates daily spending velocities and alerts you when a category's burn rate is unsustainable.
                                </p>

                                <div class="space-y-4 pt-2">
                                    <!-- Category 1: Food (Over pacing) -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-bold">
                                            <span class="text-slate-300 flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping shrink-0"></span>
                                                Food & Dining (Pacing: 196%)
                                            </span>
                                            <span class="text-rose-400 font-black">৳3,500 spent / ৳{{ simAiReallocated ? '5,900' : '5,500' }} limit</span>
                                        </div>
                                        <div class="relative w-full bg-slate-950 rounded-full h-2.5 overflow-visible border border-slate-800">
                                            <!-- Progress fill -->
                                            <div 
                                                class="h-full rounded-full bg-gradient-to-r from-rose-400 to-rose-500 transition-all duration-500" 
                                                :style="{ width: (simAiReallocated ? (3500/5900*100) : (3500/5500*100)) + '%' }"
                                            ></div>
                                            <!-- Day 10 pace marker (10 / 31 = 32.2%) -->
                                            <div 
                                                class="absolute top-[-4px] bottom-[-4px] w-[2px] bg-slate-400 z-10"
                                                style="left: 32.2%"
                                                title="Today (Day 10)"
                                            >
                                                <span class="absolute -top-4 left-1/2 -translate-x-1/2 text-[7px] bg-slate-700 text-white font-black px-1 rounded shadow">Day 10</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Category 2: Entertainment (Under pacing) -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-bold">
                                            <span class="text-slate-300 flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                                Entertainment (Pacing: 62%)
                                            </span>
                                            <span class="text-emerald-400 font-black">৳200 spent / ৳{{ simAiReallocated ? '600' : '1,000' }} limit</span>
                                        </div>
                                        <div class="relative w-full bg-slate-950 rounded-full h-2.5 overflow-visible border border-slate-800">
                                            <!-- Progress fill -->
                                            <div 
                                                class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all duration-500" 
                                                :style="{ width: (simAiReallocated ? (200/600*100) : (200/1000*100)) + '%' }"
                                            ></div>
                                            <!-- Day 10 pace marker -->
                                            <div 
                                                class="absolute top-[-4px] bottom-[-4px] w-[2px] bg-slate-400 z-10"
                                                style="left: 32.2%"
                                                title="Today (Day 10)"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Advice Card -->
                            <div class="relative overflow-hidden p-4 rounded-2xl border border-indigo-900/60 bg-gradient-to-br from-indigo-950/40 via-indigo-900/10 to-purple-950/20 text-xs text-white">
                                <div class="flex items-start gap-3">
                                    <span class="text-lg shrink-0 animate-bounce">🧠</span>
                                    <div class="space-y-1">
                                        <span class="text-[8px] uppercase font-black tracking-widest bg-indigo-500/20 border border-indigo-500/35 px-1.5 py-0.5 rounded-full text-indigo-300">Gemini Pacing Advice</span>
                                        <p class="text-slate-300 font-medium leading-relaxed mt-1">
                                            <span v-if="!simAiReallocated">
                                                Your Food & Dining category is pacing 196% faster than ideal with a pacing deficit of ৳1,500. We recommend shifting ৳400 from your under-utilized Entertainment category (which currently has a ৳600 surplus) to keep your overall budget balanced.
                                            </span>
                                            <span v-else>
                                                Pacing optimized! ৳400 has been shifted from Entertainment to Food & Dining. Your Food & Dining budget limit is now ৳5,900, bringing actual velocity back into safety bounds.
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Simulator Action -->
                            <div class="bg-indigo-950/20 border border-indigo-900/50 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div class="text-left space-y-0.5">
                                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wide">Simulator Action</span>
                                    <p class="text-xs font-bold text-slate-350">
                                        {{ simAiReallocated ? 'Reallocation Complete' : 'Execute AI Reallocation recommendation' }}
                                    </p>
                                </div>
                                <button 
                                    @click="simulateReallocation" 
                                    :disabled="simAiReallocated"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-650 hover:bg-indigo-600 text-white font-extrabold text-xs transition disabled:opacity-40"
                                >
                                    {{ simAiReallocated ? 'Balanced ✓' : 'Shift ৳400 Surplus' }}
                                </button>
                            </div>
                        </div>


                        <!-- Simulator Toast Panel -->
                        <div class="h-10 mt-3 flex items-center justify-center">
                            <Transition
                                enter-active-class="transition duration-300 ease-out"
                                enter-from-class="transform translate-y-2 opacity-0"
                                enter-to-class="transform translate-y-0 opacity-100"
                                leave-active-class="transition duration-200 ease-in"
                                leave-from-class="transform translate-y-0 opacity-100"
                                leave-to-class="transform translate-y-2 opacity-0"
                            >
                                <div 
                                    v-if="simToast" 
                                    class="px-4 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-extrabold text-indigo-400 flex items-center gap-2 shadow-lg"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-ping"></span>
                                    {{ simToast }}
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dynamic Infographics Section (Sales Copy & SVG Visualizations) -->
        <section class="border-t border-slate-900 bg-slate-900/20 py-20 relative">
            <div class="absolute top-1/2 left-1/4 w-96 h-96 bg-indigo-600/5 rounded-full blur-[100px] pointer-events-none"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">Interactive Infographics</span>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">Institutional-grade Financial Clarity.</h2>
                    <p class="text-sm text-slate-400 leading-relaxed font-semibold">
                        Cashbox turns raw transaction numbers into beautiful, interactive, and actionable dashboards on autopilot.
                    </p>
                </div>

                <!-- Three Infographic Showcases -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Infographic 1: Monthly Cash Flow SVGs -->
                    <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800 flex flex-col justify-between space-y-6">
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Visual analytics</span>
                            <h3 class="text-lg font-extrabold text-white">Monthly Cash Flow Trend</h3>
                            <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                Beautiful, custom SVG charts map out your historical income versus outflows for complete structural transparency.
                            </p>
                        </div>
                        
                        <!-- Mini SVG chart infographic -->
                        <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-850 h-44 flex items-end justify-between gap-3 text-[10px] font-bold text-slate-655 relative overflow-hidden">
                            <div class="absolute inset-0 bg-grid-pattern opacity-10 pointer-events-none"></div>
                            
                            <div class="w-full text-center space-y-2 z-10">
                                <div class="w-full h-28 bg-slate-900/60 rounded-lg relative overflow-hidden flex items-end">
                                    <div class="w-1/2 bg-rose-500/20 border-t-2 border-rose-500 h-[45%] rounded-t-sm transition-all duration-300 hover:h-[50%]"></div>
                                    <div class="w-1/2 bg-emerald-500/20 border-t-2 border-emerald-500 h-[75%] rounded-t-sm transition-all duration-300 hover:h-[80%]"></div>
                                </div>
                                <span class="text-[9px]">April</span>
                            </div>
                            <div class="w-full text-center space-y-2 z-10">
                                <div class="w-full h-28 bg-slate-900/60 rounded-lg relative overflow-hidden flex items-end">
                                    <div class="w-1/2 bg-rose-500/20 border-t-2 border-rose-500 h-[65%] rounded-t-sm transition-all duration-300 hover:h-[70%]"></div>
                                    <div class="w-1/2 bg-emerald-500/20 border-t-2 border-emerald-500 h-[60%] rounded-t-sm transition-all duration-300 hover:h-[65%]"></div>
                                </div>
                                <span class="text-[9px]">May</span>
                            </div>
                            <div class="w-full text-center space-y-2 z-10">
                                <div class="w-full h-28 bg-slate-900/60 rounded-lg relative overflow-hidden flex items-end">
                                    <div class="w-1/2 bg-rose-500/20 border-t-2 border-rose-500 h-[30%] rounded-t-sm transition-all duration-300 hover:h-[35%]"></div>
                                    <div class="w-1/2 bg-emerald-500/20 border-t-2 border-emerald-500 h-[90%] rounded-t-sm transition-all duration-300 hover:h-[95%]"></div>
                                </div>
                                <span class="text-[9px]">June</span>
                            </div>
                        </div>
                    </div>

                    <!-- Infographic 2: MRR & Subscription Renewal Cycle -->
                    <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800 flex flex-col justify-between space-y-6">
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Subscription tracking</span>
                            <h3 class="text-lg font-extrabold text-white">SaaS MRR Billing Cycles</h3>
                            <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                Track client license milestones and automatically update accounts statements when payment receipts trigger.
                            </p>
                        </div>
                        
                        <!-- Timeline billing infographic -->
                        <div class="bg-slate-955/60 p-4 rounded-2xl border border-slate-850 h-44 flex flex-col justify-between text-[10px] font-bold text-slate-500">
                            <div class="flex items-center justify-between border-b border-slate-900 pb-2">
                                <span class="text-slate-350">Client License</span>
                                <span class="text-indigo-400">MRR Share</span>
                                <span class="text-slate-600">Status</span>
                            </div>
                            
                            <div class="space-y-2.5 overflow-hidden">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-200">SpaceX (Enterprise)</span>
                                    <span class="text-indigo-400">৳25,000.00</span>
                                    <span class="text-emerald-450 bg-emerald-500/10 px-2 py-0.5 rounded text-[8px] uppercase font-black border border-emerald-500/20">Paid ✓</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-200">Tesla (Standard)</span>
                                    <span class="text-indigo-400">৳12,000.00</span>
                                    <span class="text-emerald-450 bg-emerald-500/10 px-2 py-0.5 rounded text-[8px] uppercase font-black border border-emerald-500/20">Paid ✓</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-200">Stark Labs (Pro)</span>
                                    <span class="text-indigo-400">৳3,500.00</span>
                                    <span class="text-amber-455 bg-amber-500/10 px-2 py-0.5 rounded text-[8px] uppercase font-black border border-amber-500/20">Pending</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Infographic 3: Vault / SQLite Local Storage -->
                    <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800 flex flex-col justify-between space-y-6">
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Data security</span>
                            <h3 class="text-lg font-extrabold text-white">SQLite Vault sovereignty</h3>
                            <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                                Complete privacy. Zero third-party web trackers. Run your database inside a secure local file that you own 100%.
                            </p>
                        </div>
                        
                        <!-- Safe vault visual mockup -->
                        <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-850 h-44 flex items-center justify-center relative overflow-hidden">
                            <div class="absolute inset-0 bg-radial-gradient opacity-10"></div>
                            
                            <div class="flex flex-col items-center justify-center space-y-2 z-10">
                                <div class="w-16 h-16 rounded-full border-4 border-slate-850 flex items-center justify-center relative bg-slate-900">
                                    <!-- Dial handle notches -->
                                    <div class="absolute inset-1.5 rounded-full border-2 border-indigo-500 border-dashed animate-[spin_20s_linear_infinite]"></div>
                                    <div class="w-6 h-6 rounded-full bg-slate-850 border border-indigo-400/50 flex items-center justify-center shadow-inner">
                                        <div class="w-2.5 h-2.5 rounded-full bg-indigo-550 shadow shadow-indigo-550"></div>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-indigo-400 tracking-wider">DATABASE ENCRYPTED</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Presentation Grid -->
        <section class="border-t border-slate-900 bg-slate-955/30 py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">PRODUCT FEATURES</span>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">
                        A Complete Financial OS for Modern Builders.
                    </h2>
                    <p class="text-sm text-slate-400 leading-relaxed font-semibold">
                        Here is everything Cashbox is equipped with to put your financial accounting and subscriptions on autopilot.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Feature 1: Unified Ledger -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-indigo-500/5 rounded-full blur-xl group-hover:bg-indigo-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 shadow shadow-indigo-500/10 border border-indigo-500/20">
                            <span class="text-sm">📊</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Daily Ledger & Filters</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Search descriptions, filter by type, account, and category. Date-based ledger constraints default automatically to the current month.
                        </p>
                    </div>

                    <!-- Feature 2: Category Management -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-violet-500/5 rounded-full blur-xl group-hover:bg-violet-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 shadow shadow-violet-500/10 border border-violet-500/20">
                            <span class="text-sm">🏷️</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Expense & Income Categories</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Manage separate categories lists. Create custom targets and monitor linked transactions counts directly from the category panels.
                        </p>
                    </div>

                    <!-- Feature 3: Visual Budget Alarms -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-rose-500/5 rounded-full blur-xl group-hover:bg-rose-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-455 shadow shadow-rose-500/10 border border-rose-500/20">
                            <span class="text-sm">🚨</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Limit Alerts & Alarms</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Budget limit bars shift color from green, to orange warnings (exceeding 80%), to red alarms (exceeding 100%) to restrict outflows.
                        </p>
                    </div>

                    <!-- Feature 4: Double-Entry Transfers -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl group-hover:bg-emerald-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 shadow shadow-emerald-500/10 border border-emerald-500/20">
                            <span class="text-sm">💳</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Multi-Account & Wallets</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Set up Bank, Cash, and Mobile Wallet (bKash/Nagad) accounts. Log double-entry transfers that update ending statements seamlessly.
                        </p>
                    </div>

                    <!-- Feature 5: Loans & Outstanding Debt -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-amber-500/5 rounded-full blur-xl group-hover:bg-amber-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 shadow shadow-amber-500/10 border border-amber-500/20">
                            <span class="text-sm">🤝</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Debts & Repayments</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Track what John Doe owes you or what you owe Jane. Log partial repayments that decrease outstanding debt and update cash.
                        </p>
                    </div>

                    <!-- Feature 6: SaaS MRR Manager -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-sky-500/5 rounded-full blur-xl group-hover:bg-sky-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-sky-500/10 flex items-center justify-center text-sky-400 shadow shadow-sky-500/10 border border-sky-500/20">
                            <span class="text-sm">💼</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Subscriptions & Licenses</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            List SaaS licenses, monthly costs, and renewal schedules. Log payment events that push next billing cycles forward.
                        </p>
                    </div>

                    <!-- Feature 7: Interactive Reminders Banners -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-fuchsia-500/5 rounded-full blur-xl group-hover:bg-fuchsia-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-fuchsia-500/10 flex items-center justify-center text-fuchsia-450 shadow shadow-fuchsia-500/10 border border-fuchsia-500/20">
                            <span class="text-sm">🔔</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Upcoming & Overdue Bills</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Interactive bill reminder cards on the main dashboard allow you to process payments or skip billing cycles in one click.
                        </p>
                    </div>

                    <!-- Feature 8: Savings Forecasts -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-cyan-500/5 rounded-full blur-xl group-hover:bg-cyan-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center text-cyan-400 shadow shadow-cyan-500/10 border border-cyan-500/20">
                            <span class="text-sm">📈</span>
                        </div>
                        <h3 class="text-base font-bold text-white">Projections & Forecasts</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Project ending balances over 3, 6, and 12-month periods based on rolling 3-month savings rates and net balances.
                        </p>
                    </div>

                    <!-- Feature 9: AI Budget Monitor -->
                    <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4 hover:border-slate-800 transition duration-150 hover:-translate-y-1 relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-violet-500/5 rounded-full blur-xl group-hover:bg-violet-500/10 transition duration-300"></div>
                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 shadow shadow-violet-500/10 border border-violet-500/20">
                            <span class="text-sm">🧠</span>
                        </div>
                        <h3 class="text-base font-bold text-white">AI Budget Monitor</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-semibold">
                            Compare actual spending velocity to ideal pace per day. Our integrated Gemini copilot suggests strategic envelope reallocations automatically.
                        </p>
                    </div>
                </div>

            </div>
        </section>

        <!-- Pricing Tier Grid Section (SaaS commercial appeal) -->
        <section class="border-t border-slate-900 bg-slate-900/10 py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">Pricing Tiers</span>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">Flexible plans for indie creators.</h2>
                    <p class="text-sm text-slate-400 leading-relaxed font-semibold">
                        Host Cashbox yourself for free, or choose our secure managed cloud.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    <!-- Self-Hosted Plan -->
                    <div class="p-8 bg-slate-900/40 border border-slate-850 rounded-3xl flex flex-col justify-between space-y-6 hover:border-slate-800 transition duration-150 relative overflow-hidden group">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-white">Self-Hosted Core</h3>
                                <span class="px-2.5 py-1 rounded bg-slate-850 border border-slate-750 text-xs font-bold text-slate-400">Open Source</span>
                            </div>
                            <p class="text-xs text-slate-450 leading-relaxed">
                                Complete privacy and control. Spin up your own personal instance on any local server or VPS.
                            </p>
                            <div class="flex items-baseline gap-1 text-white">
                                <span class="text-4xl font-black">৳0</span>
                                <span class="text-xs font-bold text-slate-700">/ forever</span>
                            </div>
                            
                            <hr class="border-slate-855" />
                            
                            <ul class="space-y-3.5 text-xs text-slate-400 font-semibold">
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-450" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Unlimited Accounts & Wallets
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-450" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Visual Budget Limit Warnings
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-450" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    SaaS License & MRR Manager
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-450" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Personal Loans & Debt tracker
                                </li>
                            </ul>
                        </div>
                        <a 
                            href="https://github.com/mhsohel/cashbox.git" 
                            target="_blank"
                            class="w-full py-3.5 rounded-xl bg-slate-850 hover:bg-slate-800 text-slate-200 font-bold text-xs text-center border border-slate-750 transition"
                        >
                            View Docs & Clone Repo
                        </a>
                    </div>

                    <!-- Cloud Plan -->
                    <div class="p-8 bg-slate-900/60 border-2 border-indigo-500 rounded-3xl flex flex-col justify-between space-y-6 relative overflow-hidden group shadow-xl shadow-indigo-650/5">
                        <div class="absolute -right-8 -top-8 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-white">Cashbox Cloud</h3>
                                <span class="px-2.5 py-1 rounded bg-indigo-500/20 border border-indigo-500/30 text-xs font-bold text-indigo-400">Popular</span>
                            </div>
                            <p class="text-xs text-slate-450 leading-relaxed">
                                No setup required. Sync with external bank APIs automatically, export statements, and log in on multiple devices.
                            </p>
                            <div class="flex items-baseline gap-1 text-white">
                                <span class="text-4xl font-black">৳990</span>
                                <span class="text-xs font-bold text-slate-700">/ month</span>
                            </div>
                            
                            <hr class="border-slate-855" />
                            
                            <ul class="space-y-3.5 text-xs text-slate-405 font-bold">
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-455" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <strong>Everything in Self-Hosted</strong>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-455" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Automated Bank Sync (Plaid API)
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-455" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Multi-Device Synchronization
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-455" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Priority Chat & Email Support
                                </li>
                            </ul>
                        </div>
                        
                        <Link 
                            v-if="canRegister && !$page.props.auth.user"
                            :href="route('register')"
                            class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs text-center shadow-lg shadow-indigo-600/20 transition-all duration-150 active:scale-95"
                        >
                            Try 14-Days Cloud Trial
                        </Link>
                        <Link 
                            v-else
                            :href="route('dashboard')"
                            class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs text-center shadow-lg shadow-indigo-600/20 transition-all duration-150 active:scale-95"
                        >
                            Go to Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Premium Developer Services Section -->
        <section id="premium-services" class="border-t border-slate-900 bg-slate-950/20 py-20 relative">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-500/5 rounded-full blur-[120px] pointer-events-none"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">PRANTIK-SOFT SERVICES</span>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">Need Custom Development or VPS Setup?</h2>
                    <p class="text-sm text-slate-400 leading-relaxed font-semibold">
                        Get tailored developer services directly from the creators of Cashbox to power up your business cash flows.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start max-w-5xl mx-auto">
                    <!-- Left: Services details list -->
                    <div class="lg:col-span-5 space-y-8">
                        <h3 class="text-xl font-bold text-white">Why hire our developers?</h3>
                        
                        <div class="space-y-6">
                            <!-- Service 1 -->
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 border border-indigo-500/20 shadow shadow-indigo-500/5">
                                    <span class="text-sm">💻</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white">Custom Features Development</h4>
                                    <p class="text-xs text-slate-400 mt-1 leading-relaxed font-semibold">
                                        Need specialized reports, custom transaction flows, or secondary integrations? We write clean, test-driven PHP & Vue code.
                                    </p>
                                </div>
                            </div>

                            <!-- Service 2 -->
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow shadow-emerald-500/5">
                                    <span class="text-sm">🚀</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white">Cloud Deployment & VPS Setup</h4>
                                    <p class="text-xs text-slate-400 mt-1 leading-relaxed font-semibold">
                                        We deploy Cashbox on AWS, DigitalOcean, or Linode with SQLite/PostgreSQL, SSL configs, automated backups, and sub-domain setups.
                                    </p>
                                </div>
                            </div>

                            <!-- Service 3 -->
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20 shadow shadow-amber-500/5">
                                    <span class="text-sm">🔌</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white">Local Wallet API Integration</h4>
                                    <p class="text-xs text-slate-400 mt-1 leading-relaxed font-semibold">
                                        Accept local client payments automatically. We integrate payment checkouts like bKash, Nagad, Rocket, or SSLCommerz directly.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-900/60 rounded-2xl border border-slate-850 text-[11px] text-slate-400 leading-relaxed space-y-1.5 font-bold">
                            <p class="font-extrabold text-white text-xs mb-1">Direct Developer Contacts:</p>
                            <p>📞 Phone: +8801735254295</p>
                            <p>✉️ Email: mhsohel017@gmail.com</p>
                        </div>
                    </div>

                    <!-- Right: Gorgeous form -->
                    <div class="lg:col-span-7 bg-slate-900/40 border border-slate-850 rounded-3xl p-6 md:p-8 backdrop-blur-sm">
                        <h3 class="text-lg font-black text-white mb-6">Order Premium Services</h3>

                        <Transition
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                        >
                            <div v-if="showServiceSuccess" class="mb-6 p-4 bg-emerald-950/40 border border-emerald-900/50 rounded-2xl text-center space-y-2">
                                <div class="w-10 h-10 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h4 class="text-sm font-bold text-white">Order Request Received!</h4>
                                <p class="text-xs text-slate-450 leading-relaxed font-semibold">
                                    Thank you! Your request has been successfully recorded in our leads log. PRANTIK-SOFT developers will review it and contact you shortly.
                                </p>
                            </div>
                        </Transition>

                        <form @submit.prevent="submitServiceOrder" class="space-y-4 text-xs font-semibold">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Your Name</label>
                                    <input
                                        v-model="serviceForm.name"
                                        type="text"
                                        required
                                        placeholder="Full Name"
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                    />
                                    <span v-if="serviceForm.errors.name" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.name }}</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
                                    <input
                                        v-model="serviceForm.email"
                                        type="email"
                                        required
                                        placeholder="name@company.com"
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                    />
                                    <span v-if="serviceForm.errors.email" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.email }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Service Requested</label>
                                    <select
                                        v-model="serviceForm.service_type"
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                    >
                                        <option class="bg-slate-950 text-white font-semibold" value="custom_feature">Custom Feature Development</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="deployment_setup">Cloud & VPS Deployment</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="api_integration">API & Payment Gateway Sync</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="support">Priority SLA Support</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="other">Other Requirements</option>
                                    </select>
                                    <span v-if="serviceForm.errors.service_type" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.service_type }}</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Estimated Budget</label>
                                    <select
                                        v-model="serviceForm.budget"
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                    >
                                        <option class="bg-slate-950 text-white font-semibold" value="under_500">Under $500</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="500_1500">$500 - $1,500</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="1500_5000">$1,500 - $5,000</option>
                                        <option class="bg-slate-950 text-white font-semibold" value="above_5000">Above $5,000</option>
                                    </select>
                                    <span v-if="serviceForm.errors.budget" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.budget }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Phone Number (Optional)</label>
                                <input
                                    v-model="serviceForm.phone"
                                    type="text"
                                    placeholder="+88017XXXXXXXX"
                                    class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                />
                                <span v-if="serviceForm.errors.phone" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.phone }}</span>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Detailed Project Requirements</label>
                                <textarea
                                    v-model="serviceForm.description"
                                    rows="4"
                                    required
                                    placeholder="Explain your customized requirements, timelines, etc..."
                                    class="w-full rounded-xl border border-slate-800 bg-slate-950 text-white text-xs px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{ 'opacity-50 pointer-events-none': serviceForm.processing }"
                                ></textarea>
                                <span v-if="serviceForm.errors.description" class="text-[10px] text-rose-500 mt-1 block">{{ serviceForm.errors.description }}</span>
                            </div>

                            <button
                                type="submit"
                                :disabled="serviceForm.processing"
                                class="w-full py-4 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 transition-all duration-150 active:scale-95 disabled:opacity-50 disabled:pointer-events-none"
                            >
                                <span v-if="serviceForm.processing" class="flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing Order...
                                </span>
                                <span v-else>Submit Order Request</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 space-y-12">
            <div class="text-center space-y-4 max-w-2xl mx-auto">
                <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">TESTIMONIALS</span>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Approved by indie builders.</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <!-- Testimonial 1 -->
                <div class="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl flex flex-col justify-between space-y-6">
                    <p class="text-xs text-slate-350 leading-relaxed font-semibold">
                        "The SQLite-backed local performance is insane. I self-host it on a tiny $5 VPS and page loads are completely instantaneous. Being able to audit both my micro-SaaS MRR and daily personal expenses in one unified database is amazing."
                    </p>
                    <div class="flex items-center gap-3.5 border-t border-slate-850 pt-4">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-emerald-500 flex items-center justify-center font-bold text-[10px] text-white">AK</div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Arvid K.</h4>
                            <span class="text-[9px] font-bold text-slate-500">Founder, ShipQuick</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="p-6 bg-slate-900/40 border border-slate-855 rounded-2xl flex flex-col justify-between space-y-6">
                    <p class="text-xs text-slate-350 leading-relaxed font-semibold">
                        "I used to bounce between spreadsheets and complex budgeting apps. Cashbox gives me visual alarms when my food budget is nearing the limit, and lets me track cash balances across mobile wallets (bKash) easily."
                    </p>
                    <div class="flex items-center gap-3.5 border-t border-slate-850 pt-4">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-violet-500 to-rose-500 flex items-center justify-center font-bold text-[10px] text-white">SM</div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Sarah M.</h4>
                            <span class="text-[9px] font-bold text-slate-550">Freelance UX Designer</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="p-6 bg-slate-900/40 border border-slate-855 rounded-2xl flex flex-col justify-between space-y-6">
                    <p class="text-xs text-slate-350 leading-relaxed font-semibold">
                        "The Loans & Debts module is exactly what was missing. Now when I lend money to colleagues, I register it on the portal, assign a due date, and log repayments with two clicks. It even updates my accounts ledger automatically."
                    </p>
                    <div class="flex items-center gap-3.5 border-t border-slate-850 pt-4">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-amber-500 to-indigo-500 flex items-center justify-center font-bold text-[10px] text-white">JN</div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Julian N.</h4>
                            <span class="text-[9px] font-bold text-slate-550">Co-Founder, SaaSify</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Android App Section -->
        <section class="border-t border-slate-900 bg-slate-950/40 py-20 relative overflow-hidden">
            <div class="absolute top-1/2 left-1/4 -translate-y-1/2 w-72 h-72 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute top-1/3 right-1/4 w-72 h-72 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-12 lg:gap-12 items-center">
                    
                    <!-- App Screenshot/Mockup placeholder or SVG art -->
                    <div class="lg:col-span-5 flex justify-center mb-12 lg:mb-0">
                        <div class="relative w-64 h-[480px] rounded-[36px] bg-slate-900 border-4 border-slate-800 p-3 shadow-2xl shadow-indigo-500/10 flex flex-col justify-between overflow-hidden">
                            <!-- Speaker & Camera notch -->
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-slate-800 rounded-b-2xl z-10 flex items-center justify-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                <span class="w-8 h-1 bg-slate-700 rounded-full"></span>
                            </div>

                            <!-- Mobile App Screen Simulator Mock -->
                            <div class="flex-1 rounded-[28px] bg-slate-950 p-4 pt-8 flex flex-col justify-between relative overflow-hidden">
                                <!-- Top status bar -->
                                <div class="flex justify-between items-center text-[10px] text-slate-500 font-bold mb-4">
                                    <span>20:28</span>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7a1 1 0 011.414-1.414L10 14.586l6.293-6.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        <span>4G</span>
                                    </div>
                                </div>

                                <!-- Screen Content -->
                                <div class="space-y-4 flex-1">
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="16" rx="3.5" stroke="currentColor" stroke-width="2.5" />
                                            <circle cx="12" cy="12" r="3.8" stroke="currentColor" stroke-width="1.8" />
                                        </svg>
                                        <span class="text-xs font-black text-white">CashBox Mobile</span>
                                    </div>

                                    <!-- Dummy balance -->
                                    <div class="p-3 bg-gradient-to-br from-indigo-500/20 to-indigo-700/20 border border-indigo-500/30 rounded-xl">
                                        <span class="text-[9px] font-bold text-indigo-400 block uppercase">Net Balance</span>
                                        <span class="text-lg font-black text-white">৳84,250.00</span>
                                    </div>

                                    <!-- Recent Txns -->
                                    <div class="space-y-2">
                                        <span class="text-[9px] font-bold text-slate-500 block uppercase">Recent Activity</span>
                                        <div class="p-2 bg-slate-900/60 border border-slate-800 rounded-lg flex items-center justify-between text-[10px]">
                                            <span class="text-slate-400 font-bold">Salary Income</span>
                                            <span class="text-emerald-400 font-extrabold">+৳45,000</span>
                                        </div>
                                        <div class="p-2 bg-slate-900/60 border border-slate-800 rounded-lg flex items-center justify-between text-[10px]">
                                            <span class="text-slate-400 font-bold">Groceries</span>
                                            <span class="text-rose-400 font-extrabold">-৳2,850</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Screen bottom indicator -->
                                <div class="w-20 h-1 bg-slate-800 rounded-full mx-auto mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Right: Info & Download Link -->
                    <div class="lg:col-span-7 space-y-6">
                        <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">MOBILE OS</span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                            Manage capital on the go with CashBox for Android
                        </h2>
                        <p class="text-sm sm:text-base text-slate-400 leading-relaxed font-semibold">
                            Get real-time push notifications, lock down logs instantly, and sync transactions directly from your phone. Our tailored Android application is built to run lightweight and secure.
                        </p>

                        <!-- File Info Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 py-2 border-t border-b border-slate-900">
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">File Name</span>
                                <span class="text-xs font-bold text-white">Cash_Box_BD.apk</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">Size</span>
                                <span class="text-xs font-bold text-white">6.07 MB</span>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">Compatibility</span>
                                <span class="text-xs font-bold text-white">Android 8.0+ (Oreo)</span>
                            </div>
                        </div>

                        <!-- Features list -->
                        <div class="space-y-3 pt-2">
                            <div class="flex items-start gap-2.5 text-slate-400 text-xs font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Double-entry transfers logging directly from mobile notification drawers.</span>
                            </div>
                            <div class="flex items-start gap-2.5 text-slate-400 text-xs font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Configured with Firebase Cloud Messaging (FCM) to trigger alarms instantly on budget breach.</span>
                            </div>
                            <div class="flex items-start gap-2.5 text-slate-400 text-xs font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Clean, high-contrast dark theme optimized for OLED displays.</span>
                            </div>
                        </div>

                        <div class="pt-4 flex flex-col sm:flex-row gap-4">
                            <a
                                href="/Cash_Box_BD.apk"
                                download="Cash_Box_BD.apk"
                                class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-500/10 transition-all duration-150 active:scale-95"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download Android App (APK)
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Interactive FAQ Accordion Section -->
        <section class="border-t border-slate-900 bg-slate-950/20 py-20">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">QUESTIONS & ANSWERS</span>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">Frequently Asked Questions</h2>
                </div>

                <div class="space-y-4 max-w-3xl mx-auto">
                    <div 
                        v-for="(faq, idx) in faqs" 
                        :key="idx" 
                        class="bg-slate-900/40 border border-slate-850 rounded-2xl overflow-hidden transition"
                    >
                        <button 
                            @click="toggleFaq(idx)"
                            class="w-full p-6 text-left flex justify-between items-center gap-4 hover:bg-slate-900/20 transition"
                        >
                            <span class="text-sm font-bold text-white">{{ faq.q }}</span>
                            <span class="text-indigo-400 font-extrabold text-lg">
                                {{ faq.open ? '−' : '+' }}
                            </span>
                        </button>
                        <div 
                            v-if="faq.open" 
                            class="px-6 pb-6 text-xs text-slate-400 leading-relaxed font-semibold border-t border-slate-850 pt-4"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Call to action -->
        <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <div class="bg-gradient-to-tr from-slate-950 via-slate-900 to-indigo-950 p-8 sm:p-12 rounded-3xl border border-slate-850 space-y-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Ready to get your finance flow under control?</h2>
                <p class="text-sm text-slate-400 max-w-md mx-auto leading-relaxed font-medium">
                    Set up your categories, allocate budgets, register client products, and start auditing subscription fees in minutes.
                </p>
                <div class="pt-4 flex flex-col sm:flex-row justify-center gap-3">
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="px-8 py-3.5 bg-white hover:bg-slate-100 text-slate-900 font-extrabold text-xs rounded-xl shadow-lg transition"
                    >
                        Sign Up Now
                    </Link>
                    <Link
                        v-else-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-8 py-3.5 bg-white hover:bg-slate-100 text-slate-900 font-extrabold text-xs rounded-xl shadow-lg transition"
                    >
                        Go to Dashboard
                    </Link>
                    <Link
                        :href="route('login')"
                        class="px-8 py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl border border-slate-800 transition"
                    >
                        Sign In
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-900 bg-slate-950 py-10 text-center text-xs text-slate-500 font-medium">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <div class="flex items-center justify-center gap-2">
                    <span class="font-bold text-slate-400">Cashbox</span>
                    <span>&copy; 2026. All rights reserved.</span>
                </div>
                <div class="flex items-center justify-center gap-4 text-[10px] text-slate-600">
                    <span>Powered by Laravel v{{ laravelVersion }}</span>
                    <span>PHP v{{ phpVersion }}</span>
                    <span>SQLite Storage</span>
                </div>
                <div class="flex items-center justify-center gap-4 text-[10px] text-slate-600 border-t border-slate-900 pt-4 max-w-sm mx-auto">
                    <span>Developed by:<br> <strong>PRANTIK-SOFT</strong></span>
                    <span>Mobile:<br> <strong>+8801735254295</strong></span>
                    <span>Email:<br> <strong>mhsohel017@gmail.com</strong></span>
                </div>
            </div>
        </footer>
    </div>
</template>
