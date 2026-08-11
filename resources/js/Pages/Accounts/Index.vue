<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    accounts: Array
});

// State
const showAccountModal = ref(false);
const isEditingAccount = ref(false);

const accountForm = useForm({
    id: null,
    name: '',
    type: 'bank',
    initial_balance: '',
    color: '#6366f1',
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

// Computations
const totalWealth = computed(() => {
    return props.accounts.reduce((sum, acc) => sum + acc.current_balance, 0);
});

// Actions
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
</script>

<template>
    <Head title="My Accounts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-850 dark:text-white uppercase tracking-wider">
                        My Accounts
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Manage your bank accounts, credit cards, cash wallets, and check current balances.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="openAddAccount"
                        class="px-4 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Add
                    </button>
                    <div class="bg-indigo-50 dark:bg-indigo-950/20 px-4 py-2 rounded-xl border border-indigo-100/50 dark:border-indigo-900/30 flex items-center gap-2">
                        <span class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Net Wealth:</span>
                        <span class="text-sm font-black text-indigo-650 dark:text-indigo-400">{{ formatCurrency(totalWealth) }}</span>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8 py-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <h3 class="text-sm font-bold text-slate-850 dark:text-slate-200 uppercase tracking-wider">
                            Active Financial Accounts
                        </h3>
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
                    No financial accounts registered yet. Click "Add Account" to get started.
                </div>

                <!-- Account Cards Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="acc in accounts" 
                        :key="acc.id"
                        class="w-full min-h-[190px] rounded-2xl relative p-5 text-white flex flex-col justify-between shadow-lg border border-white/10 overflow-hidden group/card hover:scale-[1.02] transition-all duration-300"
                        :style="{ background: `linear-gradient(135deg, ${acc.color || '#6366f1'} 0%, #0f172a 100%)` }"
                    >
                        <!-- Card Header -->
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <span class="text-[9px] font-bold opacity-60 uppercase tracking-widest">{{ getAccountTypeName(acc.type) }}</span>
                                <h4 class="text-sm font-bold truncate max-w-[150px]">{{ acc.name }}</h4>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button 
                                    @click="openEditAccount(acc)"
                                    class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-white transition-all flex items-center justify-center"
                                    title="Edit Account"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <button 
                                    @click="deleteAccount(acc.id)"
                                    class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-white transition-all flex items-center justify-center"
                                    title="Delete Account"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Card chip vector design -->
                        <div class="w-8 h-6 bg-amber-400/25 rounded-md border border-amber-300/35 relative z-10 flex items-center justify-center">
                            <div class="w-4 h-3 bg-amber-400/40 rounded-sm"></div>
                        </div>

                        <!-- Card Footer -->
                        <div class="flex justify-between items-end relative z-10">
                            <div>
                                <span class="text-[8px] font-semibold opacity-60 uppercase block">Account Balance</span>
                                <span class="text-lg font-black tracking-tight">{{ formatCurrency(acc.current_balance) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[8px] opacity-50 block uppercase tracking-wider">Initial</span>
                                <span class="text-xs font-bold">{{ formatCurrency(acc.initial_balance) }}</span>
                            </div>
                        </div>
                        
                        <!-- Design Circle Decorator Background -->
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/5 rounded-full pointer-events-none group-hover/card:scale-110 transition-transform duration-500"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Creator Modal -->
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
                        class="w-full h-11 bg-indigo-700 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-500/10 disabled:opacity-75 mt-2"
                    >
                        {{ isEditingAccount ? 'Modify Account Details' : 'Create New Account' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
