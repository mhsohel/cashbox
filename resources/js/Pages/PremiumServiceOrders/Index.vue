<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    orders: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

// Service requests state
const orderSearch = ref(props.filters.search || '');
const orderStatusFilter = ref(props.filters.status || '');
const orderServiceTypeFilter = ref(props.filters.service_type || '');

// Apply service orders filters
const applyOrderFilters = () => {
    router.get(
        route('premium-service-orders.index'),
        {
            search: orderSearch.value,
            status: orderStatusFilter.value,
            service_type: orderServiceTypeFilter.value,
        },
        { preserveState: true, replace: true }
    );
};

const resetOrderFilters = () => {
    orderSearch.value = '';
    orderStatusFilter.value = '';
    orderServiceTypeFilter.value = '';
    applyOrderFilters();
};

watch([orderStatusFilter, orderServiceTypeFilter], () => {
    applyOrderFilters();
});

// Update order status
const updateOrderStatus = (orderId, newStatus) => {
    router.patch(
        route('premium-service-orders.update', orderId),
        { status: newStatus },
        { preserveScroll: true }
    );
};

// Delete service request order
const deleteOrder = (orderId) => {
    if (confirm('Are you sure you want to delete this service order?')) {
        router.delete(route('premium-service-orders.destroy', orderId), {
            preserveScroll: true,
        });
    }
};

// Helper formatters
const getServiceLabel = (type) => {
    const services = {
        custom_feature: 'Custom Feature Development',
        deployment_setup: 'Cloud / VPS Setup',
        api_integration: 'API & Gateway Integration',
        support: 'Support & Maintenance',
        other: 'Other Custom Request',
    };
    return services[type] || type;
};

const getBudgetLabel = (budget) => {
    const budgets = {
        under_500: 'Under $500',
        '500_1500': '$500 - $1,500',
        '1500_5000': '$1,500 - $5,000',
        above_5000: 'Above $5,000',
    };
    return budgets[budget] || budget;
};
</script>

<template>
    <Head title="Premium Service Orders" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    Premium Service Orders Log
                </h2>
            </div>
        </template>

        <div class="py-12 animate-fade-in">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Success/Error alert banners from Controller sessions -->
                <div v-if="$page.props.flash.success" class="p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/30 rounded-2xl flex items-start gap-3 shadow-sm text-emerald-800 dark:text-emerald-400 text-xs font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-emerald-555" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>
                <div v-if="$page.props.flash.error" class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 rounded-2xl flex items-start gap-3 shadow-sm text-red-800 dark:text-red-400 text-xs font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-red-555" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ $page.props.flash.error }}</span>
                </div>

                <!-- Filters & Search -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <form @submit.prevent="applyOrderFilters" class="grid grid-cols-1 gap-4 md:grid-cols-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">Search Requests</label>
                            <input
                                v-model="orderSearch"
                                type="text"
                                placeholder="Search by client name, email..."
                                class="w-full rounded-xl border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">Service Type</label>
                            <select
                                v-model="orderServiceTypeFilter"
                                class="w-full rounded-xl border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Services</option>
                                <option value="custom_feature">Custom Feature Development</option>
                                <option value="deployment_setup">Cloud / VPS Setup</option>
                                <option value="api_integration">API & Gateway Integration</option>
                                <option value="support">Support & Maintenance</option>
                                <option value="other">Other Request</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">Status</label>
                            <select
                                v-model="orderStatusFilter"
                                class="w-full rounded-xl border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="contacted">Contacted</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="flex-1 py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold text-sm shadow transition"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                @click="resetOrderFilters"
                                class="py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 rounded-xl font-bold text-sm transition"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Orders Log -->
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-extrabold text-gray-900 dark:text-white">Leads Log</h3>
                        <span class="text-xs text-gray-450 dark:text-slate-500 font-bold">Showing {{ orders.length }} requests</span>
                    </div>

                    <div v-if="orders.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
                        <p class="text-sm font-semibold">No service orders found matching the filter criteria.</p>
                    </div>

                    <div v-else class="divide-y divide-gray-100 dark:divide-slate-800">
                        <div v-for="order in orders" :key="order.id" class="p-6 transition hover:bg-slate-50/50 dark:hover:bg-slate-850/20">
                            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                                <!-- Lead details -->
                                <div class="space-y-3.5 lg:w-1/3">
                                    <div>
                                        <h4 class="text-base font-black text-gray-900 dark:text-white">{{ order.name }}</h4>
                                        <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">Submitted on {{ new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-slate-350">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <a :href="`mailto:${order.email}`" class="hover:text-indigo-600 hover:underline font-bold">{{ order.email }}</a>
                                        </div>
                                        <div v-if="order.phone" class="flex items-center gap-2 text-xs text-gray-600 dark:text-slate-350">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <a :href="`tel:${order.phone}`" class="hover:text-indigo-655 hover:underline font-bold">{{ order.phone }}</a>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-1.5">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50">
                                            {{ getServiceLabel(order.service_type) }}
                                        </span>
                                        <span v-if="order.budget" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-105 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                            Budget: {{ getBudgetLabel(order.budget) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Description requirements -->
                                <div class="lg:w-1/2 bg-gray-50/70 dark:bg-slate-950/40 p-4 rounded-2xl border border-slate-200 dark:border-slate-855">
                                    <h5 class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2">Requirements Description</h5>
                                    <p class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-line leading-relaxed">{{ order.description }}</p>
                                </div>

                                <!-- Actions & current status -->
                                <div class="flex lg:flex-col lg:items-end justify-between gap-4 lg:w-1/6 text-right">
                                    <div>
                                        <span
                                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border"
                                            :class="{
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200 dark:border-amber-900/30': order.status === 'pending',
                                                'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-900/30': order.status === 'contacted',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/30': order.status === 'completed',
                                            }"
                                        >
                                            {{ order.status }}
                                        </span>
                                    </div>

                                    <div class="flex items-center lg:flex-col gap-2 w-full lg:w-auto">
                                        <button
                                            v-if="order.status === 'pending'"
                                            @click="updateOrderStatus(order.id, 'contacted')"
                                            class="flex-1 lg:w-full text-center px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                        >
                                            Mark Contacted
                                        </button>
                                        <button
                                            v-if="order.status !== 'completed'"
                                            @click="updateOrderStatus(order.id, 'completed')"
                                            class="flex-1 lg:w-full text-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                        >
                                            Mark Completed
                                        </button>
                                        <button
                                            @click="deleteOrder(order.id)"
                                            class="p-1.5 text-rose-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition"
                                            title="Delete Lead"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
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
