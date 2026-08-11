<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    schedules: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    accounts: {
        type: Array,
        required: true,
    },
    active_loans: {
        type: Array,
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

// Modal State
const showScheduleModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

// Tab state for filtering schedules
// 'all', 'expense', 'loan_installment', 'loan'
const activeTab = ref('all');

// Form
const form = useForm({
    type: 'expense', // 'expense', 'loan_installment', 'loan'
    frequency: 'monthly', // 'weekly', 'monthly', 'quarterly'
    amount: '',
    start_date: new Date().toISOString().split('T')[0],
    description: '',
    account_id: '',
    category_id: '',
    loan_id: '',
    loan_type: 'borrowed', // 'lent', 'borrowed'
    person_name: '',
});

// Open Add Modal
const openAddModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    
    // Set smart defaults
    form.start_date = new Date().toISOString().split('T')[0];
    if (props.categories.length > 0) {
        form.category_id = props.categories[0].id;
    }
    if (props.accounts.length > 0) {
        form.account_id = props.accounts[0].id;
    }
    if (props.active_loans.length > 0) {
        form.loan_id = props.active_loans[0].id;
    }
    showScheduleModal.value = true;
};

// Open Edit Modal
const openEditModal = (schedule) => {
    isEditing.value = true;
    editingId.value = schedule.id;
    form.clearErrors();
    
    form.type = schedule.type;
    form.frequency = schedule.frequency;
    form.amount = schedule.amount;
    form.start_date = schedule.start_date;
    form.description = schedule.description || '';
    form.account_id = schedule.account ? schedule.account.id : '';
    form.category_id = schedule.category ? schedule.category.id : '';
    form.loan_id = schedule.loan ? schedule.loan.id : '';
    form.loan_type = schedule.loan_type || 'borrowed';
    form.person_name = schedule.person_name || '';
    
    showScheduleModal.value = true;
};

// Submit Form
const submitForm = () => {
    if (isEditing.value) {
        form.patch(route('recurring.update', editingId.value), {
            onSuccess: () => {
                showScheduleModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('recurring.store'), {
            onSuccess: () => {
                showScheduleModal.value = false;
                form.reset();
            },
        });
    }
};

// Delete Schedule
const deleteSchedule = (id) => {
    if (confirm('Are you sure you want to delete this recurring schedule? This won\'t delete transactions or loans that have already been created.')) {
        router.delete(route('recurring.destroy', id));
    }
};

// Toggle Active Status
const toggleActive = (id) => {
    router.post(route('recurring.toggle', id), {}, { preserveScroll: true });
};

// Process / Pay Occurrence
const processOccurrence = (id) => {
    if (confirm('Are you sure you want to process this recurring item now? This will record the transaction/loan in the database and advance the next due date.')) {
        router.post(route('recurring.process', id), {}, { preserveScroll: true });
    }
};

// Skip Occurrence
const skipOccurrence = (id) => {
    if (confirm('Are you sure you want to skip this occurrence? This will advance the next due date without logging any transaction/loan.')) {
        router.post(route('recurring.skip', id), {}, { preserveScroll: true });
    }
};

// Filtered schedules
const filteredSchedules = computed(() => {
    if (activeTab.value === 'all') {
        return props.schedules;
    }
    return props.schedules.filter(s => s.type === activeTab.value);
});
</script>

<template>
    <Head title="Recurring Schedules & Reminders" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Recurring Items
                </h2>
                <div class="flex items-center gap-3">
                    <button 
                        @click="openAddModal"
                        class="px-4 py-2.5 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md transition duration-150 flex items-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New 
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Filters Tab -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-950 p-1.5 rounded-xl">
                        <button 
                            @click="activeTab = 'all'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'all' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            All Schedules
                        </button>
                        <button 
                            @click="activeTab = 'expense'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'expense' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            Expenses
                        </button>
                        <button 
                            @click="activeTab = 'loan_installment'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'loan_installment' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            Installments
                        </button>
                        <button 
                            @click="activeTab = 'loan'"
                            class="px-4 py-2 text-xs font-bold rounded-lg transition"
                            :class="activeTab === 'loan' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                        >
                            Borrows/Lents
                        </button>
                    </div>
                </div>

                <!-- Schedules List -->
                <div v-if="filteredSchedules.length === 0" class="flex flex-col items-center justify-center p-12 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl text-center shadow-sm">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center text-indigo-500 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">No recurring items found</h3>
                    <p class="text-sm text-slate-700 dark:text-slate-600 mt-1 max-w-sm">Create schedules for your recurring expenses, installments, or borrowings to track them with reminders.</p>
                    <button 
                        @click="openAddModal"
                        class="mt-4 px-4 py-2 bg-indigo-600 dark:bg-indigo-650 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition"
                    >
                        Create First Item
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 gap-6">
                    <div 
                        v-for="schedule in filteredSchedules" 
                        :key="schedule.id" 
                        class="bg-white dark:bg-slate-900 rounded-2xl border shadow-sm transition p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6"
                        :class="[
                            schedule.is_active ? 'border-slate-100 dark:border-slate-800' : 'border-slate-200 dark:border-slate-800 opacity-60 bg-slate-50/50 dark:bg-slate-900/50'
                        ]"
                    >
                        <!-- Details -->
                        <div class="flex items-start gap-4">
                            <div 
                                class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                                :class="[
                                    schedule.type === 'expense' ? 'bg-rose-550/10 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' : '',
                                    schedule.type === 'loan_installment' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : '',
                                    schedule.type === 'loan' ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : '',
                                ]"
                            >
                                <svg v-if="schedule.type === 'expense'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <svg v-else-if="schedule.type === 'loan_installment'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <svg v-else-if="schedule.type === 'loan'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-lg font-bold text-slate-900 dark:text-white">
                                        {{ formatCurrency(schedule.amount) }}
                                    </h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-100">
                                        {{ schedule.frequency }}
                                    </span>
                                    <span v-if="!schedule.is_active" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-600">
                                        Paused
                                    </span>
                                    <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400">
                                        Active
                                    </span>
                                </div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200 mt-0.5">
                                    <span v-if="schedule.type === 'expense'">
                                        Expense &bull; Category: <span class="font-semibold" :style="{ color: schedule.category?.color || '#3b82f6' }">{{ schedule.category?.name || 'Uncategorized' }}</span>
                                    </span>
                                    <span v-else-if="schedule.type === 'loan_installment'">
                                        Installment &bull; Repayment to: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ schedule.loan?.person_name }}</span> ({{ schedule.loan?.type === 'lent' ? 'Lent' : 'Borrowed' }})
                                    </span>
                                    <span v-else-if="schedule.type === 'loan'">
                                        {{ schedule.loan_type === 'lent' ? 'Lending to' : 'Borrowing from' }}: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ schedule.person_name }}</span>
                                    </span>
                                </p>
                                <p v-if="schedule.description" class="text-xs text-slate-600 dark:text-slate-350 mt-1 italic">
                                    "{{ schedule.description }}"
                                </p>
                            </div>
                        </div>

                        <!-- Schedule Dates -->
                        <div class="flex items-center gap-6 text-xs text-slate-600 dark:text-slate-350 flex-wrap lg:justify-end">
                            <div>
                                <p class="font-semibold text-slate-700 dark:text-slate-600 uppercase tracking-wider text-[10px]">Start Date</p>
                                <p class="mt-0.5 font-bold text-slate-700 dark:text-slate-100">{{ schedule.start_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-700 dark:text-slate-600 uppercase tracking-wider text-[10px]">Last Run</p>
                                <p class="mt-0.5 font-bold text-slate-700 dark:text-slate-100">{{ schedule.last_run_date || 'Never' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-700 dark:text-slate-600 uppercase tracking-wider text-[10px]">Next Due</p>
                                <p class="mt-0.5 font-extrabold text-indigo-600 dark:text-indigo-400">{{ schedule.next_due_date }}</p>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex items-center gap-3 justify-end shrink-0 pt-4 lg:pt-0 border-t border-slate-100 dark:border-slate-800 lg:border-t-0">
                            <!-- Process & Skip (Only if active) -->
                            <template v-if="schedule.is_active">
                                <button 
                                    @click="processOccurrence(schedule.id)"
                                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
                                    title="Process payment manually now"
                                >
                                    Process
                                </button>
                                <button 
                                    @click="skipOccurrence(schedule.id)"
                                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-950 dark:hover:bg-slate-950 text-slate-700 dark:text-slate-100 text-xs font-bold rounded-xl shadow-sm transition"
                                    title="Skip this occurrence"
                                >
                                    Skip
                                </button>
                            </template>

                            <!-- Pause / Resume -->
                            <button 
                                @click="toggleActive(schedule.id)"
                                class="p-2 hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
                                :title="schedule.is_active ? 'Pause Schedule' : 'Resume Schedule'"
                            >
                                <svg v-if="schedule.is_active" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>

                            <!-- Edit -->
                            <button 
                                @click="openEditModal(schedule)"
                                class="p-2 hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition text-slate-700 hover:text-slate-700 dark:text-slate-600 dark:hover:text-slate-300"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>

                            <!-- Delete -->
                            <button 
                                @click="deleteSchedule(schedule.id)"
                                class="p-2 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition text-red-500"
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

        <!-- Add/Edit Recurring Item Modal -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-55 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-black text-slate-900 dark:text-white">
                        {{ isEditing ? 'Edit Recurring Item' : 'New Recurring Item' }}
                    </h3>
                    <button 
                        @click="showScheduleModal = false"
                        class="p-2 hover:bg-slate-150 dark:hover:bg-slate-700 rounded-xl transition text-slate-600 dark:text-slate-700 hover:text-slate-650"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Schedule Type -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-2">Schedule Type</label>
                        <div class="grid grid-cols-3 gap-2 p-1 bg-slate-100 dark:bg-slate-950 rounded-xl">
                            <button 
                                type="button"
                                @click="form.type = 'expense'"
                                class="py-2 text-xs font-bold rounded-lg transition"
                                :class="form.type === 'expense' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                            >
                                Expense
                            </button>
                            <button 
                                type="button"
                                @click="form.type = 'loan_installment'"
                                class="py-2 text-xs font-bold rounded-lg transition"
                                :class="form.type === 'loan_installment' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                            >
                                Installment
                            </button>
                            <button 
                                type="button"
                                @click="form.type = 'loan'"
                                class="py-2 text-xs font-bold rounded-lg transition"
                                :class="form.type === 'loan' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                            >
                                Borrow/Lent
                            </button>
                        </div>
                        <span v-if="form.errors.type" class="text-xs text-red-500 mt-1 block">{{ form.errors.type }}</span>
                    </div>

                    <!-- Amount & Frequency -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Amount</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                id="amount"
                                v-model="form.amount"
                                required
                                placeholder="0.00"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <span v-if="form.errors.amount" class="text-xs text-red-500 mt-1 block">{{ form.errors.amount }}</span>
                        </div>

                        <div>
                            <label for="frequency" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Frequency</label>
                            <select 
                                id="frequency"
                                v-model="form.frequency"
                                required
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                            </select>
                            <span v-if="form.errors.frequency" class="text-xs text-red-500 mt-1 block">{{ form.errors.frequency }}</span>
                        </div>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label for="start_date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Start Date / Next Due</label>
                        <input 
                            type="date" 
                            id="start_date"
                            v-model="form.start_date"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <span v-if="form.errors.start_date" class="text-xs text-red-500 mt-1 block">{{ form.errors.start_date }}</span>
                    </div>

                    <!-- Category (Only for Expense) -->
                    <div v-if="form.type === 'expense'">
                        <label for="category_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Category</label>
                        <select 
                            id="category_id"
                            v-model="form.category_id"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="" disabled>Select a category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }} ({{ cat.type }})</option>
                        </select>
                        <span v-if="form.errors.category_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.category_id }}</span>
                    </div>

                    <!-- Active Loan (Only for Loan Installment) -->
                    <div v-if="form.type === 'loan_installment'">
                        <label for="loan_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Active Loan</label>
                        <select 
                            id="loan_id"
                            v-model="form.loan_id"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="" disabled>Select an active loan</option>
                            <option v-for="l in active_loans" :key="l.id" :value="l.id">{{ l.person_name }} &bull; {{ formatCurrency(l.amount) }} ({{ l.type }})</option>
                        </select>
                        <span v-if="form.errors.loan_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.loan_id }}</span>
                    </div>

                    <!-- New Loan Details (Only for Loan/Borrow) -->
                    <div v-if="form.type === 'loan'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Type</label>
                                <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-950 rounded-xl">
                                    <button 
                                        type="button"
                                        @click="form.loan_type = 'borrowed'"
                                        class="py-1.5 text-xs font-bold rounded-lg transition"
                                        :class="form.loan_type === 'borrowed' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                                    >
                                        Borrowed
                                    </button>
                                    <button 
                                        type="button"
                                        @click="form.loan_type = 'lent'"
                                        class="py-1.5 text-xs font-bold rounded-lg transition"
                                        :class="form.loan_type === 'lent' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-sm' : 'text-slate-700 hover:text-slate-700 dark:text-slate-600'"
                                    >
                                        Lent
                                    </button>
                                </div>
                                <span v-if="form.errors.loan_type" class="text-xs text-red-500 mt-1 block">{{ form.errors.loan_type }}</span>
                            </div>

                            <div>
                                <label for="person_name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Person Name</label>
                                <input 
                                    type="text" 
                                    id="person_name"
                                    v-model="form.person_name"
                                    required
                                    placeholder="Friend, Bank etc."
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <span v-if="form.errors.person_name" class="text-xs text-red-500 mt-1 block">{{ form.errors.person_name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Associated Account (Optional/Required depending on type) -->
                    <div>
                        <label for="account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">
                            Account 
                            <span class="text-[10px] text-slate-700 dark:text-slate-600">
                                {{ form.type === 'loan' ? '(Optional: to log initial transaction)' : '(To log transactions)' }}
                            </span>
                        </label>
                        <select 
                            id="account_id"
                            v-model="form.account_id"
                            :required="form.type === 'expense' || form.type === 'loan_installment'"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Select an account</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                        </select>
                        <span v-if="form.errors.account_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.account_id }}</span>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-600 mb-1.5">Description (Optional)</label>
                        <textarea 
                            id="description"
                            v-model="form.description"
                            rows="2"
                            placeholder="Add reference details..."
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-950 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                        <span v-if="form.errors.description" class="text-xs text-red-500 mt-1 block">{{ form.errors.description }}</span>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800 mt-6">
                        <button 
                            type="button"
                            @click="showScheduleModal = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-950 dark:hover:bg-slate-950 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50"
                        >
                            {{ isEditing ? 'Save Changes' : 'Create Schedule' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
