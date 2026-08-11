<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    clients: {
        type: Array,
        required: true,
    },
    licenses: {
        type: Array,
        required: true,
    },
    payments: {
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
const showClientModal = ref(false);
const showLicenseModal = ref(false);
const showPaymentModal = ref(false);
const selectedLicenseForPayment = ref(null);
const isEditingLicense = ref(false);
const editingLicenseId = ref(null);

// Forms
const clientForm = useForm({
    name: '',
    email: '',
    saas_name: '',
});

const licenseForm = useForm({
    client_id: '',
    amount: '',
    billing_cycle: 'monthly',
    next_renewal_date: new Date().toISOString().split('T')[0],
    status: 'active',
});

const paymentForm = useForm({
    amount: '',
    account_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
    advance_renewal: true,
});

// Submit Client
const submitClient = () => {
    clientForm.post(route('clients.store'), {
        onSuccess: () => {
            showClientModal.value = false;
            clientForm.reset();
        },
    });
};

// Delete Client
const deleteClient = (id) => {
    if (confirm('Are you sure you want to delete this client? This will delete all licenses associated with them.')) {
        router.delete(route('clients.destroy', id));
    }
};

// Open New License Modal
const openNewLicenseModal = () => {
    isEditingLicense.value = false;
    editingLicenseId.value = null;
    licenseForm.reset();
    licenseForm.clearErrors();
    showLicenseModal.value = true;
};

// Open Edit License Modal
const openEditLicenseModal = (lic) => {
    isEditingLicense.value = true;
    editingLicenseId.value = lic.id;
    licenseForm.client_id = lic.client_id;
    licenseForm.amount = lic.amount;
    licenseForm.billing_cycle = lic.billing_cycle;
    licenseForm.next_renewal_date = lic.next_renewal_date;
    licenseForm.status = lic.status;
    licenseForm.clearErrors();
    showLicenseModal.value = true;
};

// Submit License
const submitLicense = () => {
    if (isEditingLicense.value) {
        licenseForm.patch(route('licenses.update', editingLicenseId.value), {
            onSuccess: () => {
                showLicenseModal.value = false;
                licenseForm.reset();
                isEditingLicense.value = false;
                editingLicenseId.value = null;
            },
        });
    } else {
        licenseForm.post(route('licenses.store'), {
            onSuccess: () => {
                showLicenseModal.value = false;
                licenseForm.reset();
            },
        });
    }
};

// Delete License
const deleteLicense = (id) => {
    if (confirm('Are you sure you want to remove this license agreement?')) {
        router.delete(route('licenses.destroy', id));
    }
};

// Open Log Payment Modal
const openPaymentModal = (lic) => {
    selectedLicenseForPayment.value = lic;
    paymentForm.amount = lic.amount;
    paymentForm.account_id = props.accounts.length > 0 ? props.accounts[0].id : '';
    paymentForm.transaction_date = new Date().toISOString().split('T')[0];
    paymentForm.description = `SaaS License Payment - ${lic.client_name} (${lic.saas_name})`;
    paymentForm.advance_renewal = true;
    paymentForm.clearErrors();
    showPaymentModal.value = true;
};

// Submit Log Payment
const submitPayment = () => {
    paymentForm.post(route('licenses.pay', selectedLicenseForPayment.value.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        },
    });
};

// Filter states
const selectedClientFilter = ref('');
const searchQuery = ref('');

// Filtered Payments
const filteredPayments = computed(() => {
    return props.payments.filter((payment) => {
        const matchesClient = !selectedClientFilter.value || payment.client_id === Number(selectedClientFilter.value);
        const matchesSearch = !searchQuery.value || 
            payment.client_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            payment.saas_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            payment.description.toLowerCase().includes(searchQuery.value.toLowerCase());
        return matchesClient && matchesSearch;
    });
});
</script>

<template>
    <Head title="SaaS License Manager" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    SaaS License Manager
                </h2>
                <div class="flex items-center gap-3">
                    <button 
                        @click="showClientModal = true"
                        class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-800 dark:text-slate-200 font-bold text-xs rounded-xl transition duration-150 flex items-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Add Client
                    </button>
                    <button 
                        @click="openNewLicenseModal"
                        class="px-4 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                        id="add-license-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        New License
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- KPI Section -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Active Subscriptions</p>
                        <h4 class="text-3xl font-extrabold mt-1 text-indigo-600 dark:text-indigo-400">
                            {{ stats.active_count }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Clients currently paying fee</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Monthly Recurring Revenue (MRR)</p>
                        <h4 class="text-3xl font-extrabold mt-1 text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(stats.mrr) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Combined monthly SaaS earnings</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Annual Recurring Revenue (ARR)</p>
                        <h4 class="text-3xl font-extrabold mt-1 text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(stats.arr) }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">MRR extrapolated yearly</span>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-600 text-xs font-semibold uppercase tracking-wider">Renewals Due</p>
                        <h4 class="text-3xl font-extrabold mt-1" :class="stats.due_soon_count > 0 ? 'text-amber-500 animate-pulse' : 'text-slate-700 dark:text-slate-350'">
                            {{ stats.due_soon_count }}
                        </h4>
                        <span class="text-[10px] text-slate-600 dark:text-slate-700 font-medium">Renewals due in next 7 days</span>
                    </div>
                </div>

                <!-- Main Licenses Ledger -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Active Licenses & Subscriptions
                    </h3>

                    <div v-if="licenses.length === 0" class="py-16 text-center">
                        <div class="bg-indigo-50 dark:bg-slate-950 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6M12 9v6m-7 6h14a2 2 0 002-2V9a2 2 0 00-2-2h-3l-2-2H9L7 7H4a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="text-slate-700 dark:text-slate-600 font-medium">No license agreements configured.</p>
                        <p class="text-slate-600 dark:text-slate-700 text-xs mt-1">Register a client first, then configure their license to start tracking subscriptions.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-4">Client Name</th>
                                    <th class="py-3 px-4">SaaS Product</th>
                                    <th class="py-3 px-4 text-center">Billing Cycle</th>
                                    <th class="py-3 px-4 text-right">Fee Amount</th>
                                    <th class="py-3 px-4 text-center">Next Renewal</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                                <tr 
                                    v-for="lic in licenses" 
                                    :key="lic.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <td class="py-3.5 px-4 font-bold text-slate-850 dark:text-slate-100">
                                        {{ lic.client_name }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-600">
                                        {{ lic.saas_name }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap uppercase">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-750 text-slate-600 dark:text-slate-650 border border-slate-200/50 dark:border-slate-800">
                                            {{ lic.billing_cycle }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-extrabold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                        {{ formatCurrency(lic.amount) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-bold text-xs whitespace-nowrap">
                                        {{ lic.next_renewal_date }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                            :class="lic.status === 'active' 
                                                ? 'bg-emerald-50 border border-emerald-100 text-emerald-600 dark:bg-emerald-950/20 dark:border-emerald-900/40 dark:text-emerald-450' 
                                                : 'bg-rose-50 border border-rose-100 text-rose-600 dark:bg-rose-950/20 dark:border-rose-900/40 dark:text-rose-450'"
                                        >
                                            {{ lic.status }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- Log Payment -->
                                            <button 
                                                v-if="lic.status === 'active'"
                                                @click="openPaymentModal(lic)"
                                                class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/30 dark:hover:bg-emerald-900/40 dark:text-emerald-400 dark:border-emerald-900 font-bold text-[11px] rounded-lg transition"
                                                title="Record Renewal Payment"
                                                :id="'pay-license-btn-' + lic.id"
                                            >
                                                Log Payment
                                            </button>
                                            <!-- Edit -->
                                            <button 
                                                @click="openEditLicenseModal(lic)"
                                                class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg transition"
                                                title="Edit"
                                                :id="'edit-license-btn-' + lic.id"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="deleteLicense(lic.id)"
                                                class="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-600 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg transition"
                                                title="Delete"
                                                :id="'delete-license-btn-' + lic.id"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                <!-- Clients List Panel -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6">
                        Registered SaaS Clients
                    </h3>

                    <div v-if="clients.length === 0" class="py-6 text-center text-slate-600 dark:text-slate-700 italic text-xs font-semibold">
                        No clients registered yet. Press "Add Client" to start.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div 
                            v-for="cl in clients" 
                            :key="cl.id"
                            class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/20 flex flex-col justify-between"
                        >
                            <div>
                                <h4 class="font-extrabold text-slate-800 dark:text-slate-100">{{ cl.name }}</h4>
                                <p class="text-xs text-slate-450 dark:text-slate-600 mt-0.5">{{ cl.email || 'No email provided' }}</p>
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 border border-indigo-150 text-indigo-700 dark:bg-slate-950 dark:border-slate-850 dark:text-indigo-400 mt-3">
                                    SaaS: {{ cl.saas_name }}
                                </span>
                            </div>
                            <div class="flex items-center justify-end mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                <button 
                                    @click="deleteClient(cl.id)"
                                    class="text-xs font-bold text-rose-600 dark:text-rose-405 hover:underline"
                                >
                                    Delete Client
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Payment Ledger Report -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-md border border-slate-100 dark:border-slate-800 p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2050/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                SaaS Payment History & Ledger
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-700 mt-1">Audit log of all subscription payments collected from clients</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <!-- Client Filter -->
                            <div class="w-full sm:w-48">
                                <select 
                                    v-model="selectedClientFilter"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Clients</option>
                                    <option v-for="cl in clients" :key="cl.id" :value="cl.id">
                                        {{ cl.name }}
                                    </option>
                                </select>
                            </div>
                            <!-- Search -->
                            <div class="relative w-full sm:w-64">
                                <input 
                                    type="text" 
                                    v-model="searchQuery"
                                    placeholder="Search payments..."
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-600 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredPayments.length === 0" class="py-12 text-center">
                        <div class="bg-slate-50 dark:bg-slate-950 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="text-slate-700 dark:text-slate-600 text-sm font-medium">No payments found</p>
                        <p class="text-slate-600 dark:text-slate-700 text-xs mt-0.5">Payments appear here when you click "Log Payment" on active licenses.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-2.5 px-4">Date</th>
                                    <th class="py-2.5 px-4">Client</th>
                                    <th class="py-2.5 px-4">SaaS Product</th>
                                    <th class="py-2.5 px-4">Billing Cycle</th>
                                    <th class="py-2.5 px-4">Description</th>
                                    <th class="py-2.5 px-4 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                                <tr 
                                    v-for="payment in filteredPayments" 
                                    :key="payment.id"
                                    class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors"
                                >
                                    <td class="py-3 px-4 font-semibold text-slate-700 dark:text-slate-600">
                                        {{ payment.transaction_date }}
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-100">
                                        {{ payment.client_name }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-650 dark:text-slate-350">
                                        {{ payment.saas_name }}
                                    </td>
                                    <td class="py-3 px-4 uppercase font-bold text-[10px]">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-750 text-slate-600 dark:text-slate-650 border border-slate-200/50 dark:border-slate-800">
                                            {{ payment.billing_cycle }} 
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-550 dark:text-slate-600 font-medium">
                                        {{ payment.description }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-extrabold text-emerald-600 dark:text-emerald-450 text-sm">
                                        +{{ formatCurrency(payment.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ADD CLIENT MODAL -->
        <div v-if="showClientModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            Register Client
                        </h3>
                        <button 
                            @click="showClientModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitClient">
                        <div class="p-6 space-y-4">
                            <!-- Client Name -->
                            <div>
                                <label for="client-name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Client Name</label>
                                <input 
                                    type="text" 
                                    id="client-name" 
                                    v-model="clientForm.name"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="ACME Corp, John Doe..."
                                    required
                                />
                                <div v-if="clientForm.errors.name" class="text-rose-500 text-xs mt-1">{{ clientForm.errors.name }}</div>
                            </div>

                            <!-- Client Email -->
                            <div>
                                <label for="client-email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Client Email</label>
                                <input 
                                    type="email" 
                                    id="client-email" 
                                    v-model="clientForm.email"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="billing@client.com"
                                />
                                <div v-if="clientForm.errors.email" class="text-rose-500 text-xs mt-1">{{ clientForm.errors.email }}</div>
                            </div>

                            <!-- SaaS Product Name -->
                            <div>
                                <label for="saas-name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SaaS Product Name</label>
                                <input 
                                    type="text" 
                                    id="saas-name" 
                                    v-model="clientForm.saas_name"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="HostPortal, APIMonitor..."
                                    required
                                />
                                <div v-if="clientForm.errors.saas_name" class="text-rose-500 text-xs mt-1">{{ clientForm.errors.saas_name }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button type="button" @click="showClientModal = false" class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition" :disabled="clientForm.processing">
                                {{ clientForm.processing ? 'Registering...' : 'Register' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ADD LICENSE MODAL -->
        <div v-if="showLicenseModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                            {{ isEditingLicense ? 'Edit License' : 'Configure License' }}
                        </h3>
                        <button 
                            @click="showLicenseModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitLicense">
                        <div class="p-6 space-y-4">
                            <!-- Client Dropdown -->
                            <div>
                                <label for="license-client" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Select Client</label>
                                <select 
                                    id="license-client" 
                                    v-model="licenseForm.client_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                >
                                    <option value="" disabled>Choose a Client...</option>
                                    <option v-for="cl in clients" :key="cl.id" :value="cl.id">
                                        {{ cl.name }} ({{ cl.saas_name }})
                                    </option>
                                </select>
                                <div v-if="licenseForm.errors.client_id" class="text-rose-500 text-xs mt-1">{{ licenseForm.errors.client_id }}</div>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label for="license-amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">License Fee (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="license-amount" 
                                    v-model="licenseForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="0.00"
                                    required
                                    min="0.01"
                                />
                                <div v-if="licenseForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ licenseForm.errors.amount }}</div>
                            </div>

                            <!-- Billing Cycle -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Billing Cycle</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button 
                                        type="button"
                                        @click="licenseForm.billing_cycle = 'monthly'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition"
                                        :class="licenseForm.billing_cycle === 'monthly' 
                                            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 dark:bg-slate-950 dark:border-indigo-500 dark:text-indigo-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="cycle-monthly-btn"
                                    >
                                        Monthly
                                    </button>
                                    <button 
                                        type="button"
                                        @click="licenseForm.billing_cycle = 'yearly'"
                                        class="py-2.5 rounded-xl text-sm font-bold border transition"
                                        :class="licenseForm.billing_cycle === 'yearly' 
                                            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 dark:bg-slate-950 dark:border-indigo-500 dark:text-indigo-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="cycle-yearly-btn"
                                    >
                                        Yearly
                                    </button>
                                </div>
                            </div>

                            <!-- Next Renewal Date -->
                            <div>
                                <label for="license-renewal" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                    {{ isEditingLicense ? 'Next Renewal Date' : 'First Renewal Date' }}
                                </label>
                                <input 
                                    type="date" 
                                    id="license-renewal" 
                                    v-model="licenseForm.next_renewal_date"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                />
                                <div v-if="licenseForm.errors.next_renewal_date" class="text-rose-500 text-xs mt-1">{{ licenseForm.errors.next_renewal_date }}</div>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">License Status</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button 
                                        type="button"
                                        @click="licenseForm.status = 'active'"
                                        class="py-2 rounded-xl text-sm font-bold border transition"
                                        :class="licenseForm.status === 'active' 
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-emerald-950/20 dark:border-emerald-500 dark:text-emerald-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="status-active-btn"
                                    >
                                        Active
                                    </button>
                                    <button 
                                        type="button"
                                        @click="licenseForm.status = 'inactive'"
                                        class="py-2 rounded-xl text-sm font-bold border transition"
                                        :class="licenseForm.status === 'inactive' 
                                            ? 'bg-rose-50 border-rose-500 text-rose-700 dark:bg-rose-950/20 dark:border-rose-500 dark:text-rose-400' 
                                            : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-slate-650 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                        id="status-inactive-btn"
                                    >
                                        Inactive
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button type="button" @click="showLicenseModal = false" class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-sm rounded-xl shadow-md transition" :disabled="licenseForm.processing">
                                {{ licenseForm.processing ? (isEditingLicense ? 'Saving...' : 'Configuring...') : (isEditingLicense ? 'Save Changes' : 'Configure') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- LOG PAYMENT MODAL -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-md mx-auto my-6 px-4">
                <div class="relative flex flex-col w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl outline-none focus:outline-none">
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 rounded-t">
                        <div>
                            <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                                Log SaaS Payment
                            </h3>
                            <p v-if="selectedLicenseForPayment" class="text-xs text-slate-450 dark:text-slate-600 mt-1">
                                Client: {{ selectedLicenseForPayment.client_name }} ({{ selectedLicenseForPayment.saas_name }})
                            </p>
                        </div>
                        <button 
                            @click="showPaymentModal = false"
                            class="p-1 text-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitPayment">
                        <div class="p-6 space-y-4">
                            <!-- Amount -->
                            <div>
                                <label for="payment-amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Amount Received (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="payment-amount" 
                                    v-model="paymentForm.amount"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="0.00"
                                    required
                                    min="0.01"
                                />
                                <div v-if="paymentForm.errors.amount" class="text-rose-500 text-xs mt-1">{{ paymentForm.errors.amount }}</div>
                            </div>

                            <!-- Account Select -->
                            <div>
                                <label for="payment-account" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Deposit Account</label>
                                <select 
                                    id="payment-account" 
                                    v-model="paymentForm.account_id"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">No Account / Unallocated</option>
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} ({{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                                <div v-if="paymentForm.errors.account_id" class="text-rose-500 text-xs mt-1">{{ paymentForm.errors.account_id }}</div>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label for="payment-date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Payment Date</label>
                                <input 
                                    type="date" 
                                    id="payment-date" 
                                    v-model="paymentForm.transaction_date"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                />
                                <div v-if="paymentForm.errors.transaction_date" class="text-rose-500 text-xs mt-1">{{ paymentForm.errors.transaction_date }}</div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="payment-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Description / Audit Note</label>
                                <textarea 
                                    id="payment-desc" 
                                    v-model="paymentForm.description"
                                    rows="2"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Part payment details..."
                                ></textarea>
                                <div v-if="paymentForm.errors.description" class="text-rose-500 text-xs mt-1">{{ paymentForm.errors.description }}</div>
                            </div>

                            <!-- Advance Renewal Checkbox -->
                            <div class="flex items-start mt-2">
                                <div class="flex items-center h-5">
                                    <input 
                                        id="advance-renewal-chk" 
                                        type="checkbox" 
                                        v-model="paymentForm.advance_renewal" 
                                        class="h-5 w-5 rounded border-slate-300 dark:border-slate-800 text-indigo-650 focus:ring-indigo-500 bg-slate-50 dark:bg-slate-950"
                                    />
                                </div>
                                <div class="ms-3 text-xs">
                                    <label for="advance-renewal-chk" class="font-bold text-slate-800 dark:text-slate-100">Advance Renewal Date</label>
                                    <p class="text-slate-600 dark:text-slate-700 font-medium">Check this if this payment completes the billing cycle and you want to advance the next renewal date.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end p-6 border-t border-slate-100 dark:border-slate-800 rounded-b gap-3">
                            <button type="button" @click="showPaymentModal = false" class="px-4 py-2 text-slate-700 dark:text-slate-600 font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-emerald-600 dark:bg-emerald-650 hover:bg-emerald-700 dark:hover:bg-emerald-600 text-white font-bold text-sm rounded-xl shadow-md transition" :disabled="paymentForm.processing">
                                {{ paymentForm.processing ? 'Saving...' : 'Log Payment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
