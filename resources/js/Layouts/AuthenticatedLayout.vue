<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const isSidebarOpen = ref(false);

const page = usePage();
const hasModulePermission = (moduleName) => {
    const user = page.props.auth.user;
    if (!user) return false;
    if (user.is_superadmin) return true;
    
    const permissions = user.module_permissions;
    if (!permissions) {
        return moduleName !== 'licenses';
    }
    
    return permissions[moduleName] !== false;
};
const firebaseConfig = page.props.firebase;
const notificationPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'denied');
const isSubscribing = ref(false);

const requestNotificationPermission = async () => {
    if (typeof Notification === 'undefined') return;
    
    isSubscribing.value = true;
    try {
        const permission = await Notification.requestPermission();
        notificationPermission.value = permission;
        
        if (permission === 'granted') {
            await registerPush();
        }
    } catch (error) {
        console.error('Error requesting notification permission:', error);
    } finally {
        isSubscribing.value = false;
    }
};

const registerPush = async () => {
    if (!firebaseConfig || !firebaseConfig.apiKey) {
        console.warn('Firebase configuration is not set.');
        return;
    }
    
    try {
        const queryString = new URLSearchParams(firebaseConfig).toString();
        const registration = await navigator.serviceWorker.register(
            `/firebase-messaging-sw.js?${queryString}`,
            { scope: '/' }
        );
        
        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);
        
        const token = await getToken(messaging, {
            serviceWorkerRegistration: registration,
            vapidKey: firebaseConfig.vapidKey
        });
        
        if (token) {
            await window.axios.post(route('profile.fcm-token'), { fcm_token: token });
            console.log('FCM Token registered successfully.');
        }
        
        onMessage(messaging, (payload) => {
            if (Notification.permission === 'granted' && registration && payload) {
                const title = payload.notification?.title || payload.data?.title || 'Reminder Alert';
                const body = payload.notification?.body || payload.data?.body || 'You have an upcoming reminder.';
                registration.showNotification(title, {
                    body: body,
                    icon: '/favicon.ico',
                    badge: '/favicon.ico',
                    data: payload.data
                });
            }
        });
        
    } catch (err) {
        console.error('An error occurred while retrieving token:', err);
    }
};

onMounted(() => {
    if (typeof Notification !== 'undefined') {
        if (Notification.permission === 'granted') {
            registerPush();
        } else if (Notification.permission === 'default') {
            requestNotificationPermission();
        }
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex">
        <!-- Sidebar Backdrop (for Mobile) -->
        <div 
            v-if="isSidebarOpen" 
            @click="isSidebarOpen = false" 
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden transition-opacity duration-300"
        ></div>

        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-900 border-r border-slate-800 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:fixed lg:inset-y-0 lg:left-0',
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo Section -->
            <div class="flex h-16 shrink-0 items-center border-b border-slate-800 px-6 gap-3">
                <Link :href="route('dashboard')" class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24">
                        <defs>
                            <linearGradient id="sideVaultGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#818CF8" />
                                <stop offset="50%" stop-color="#C084FC" />
                                <stop offset="100%" stop-color="#FB7185" />
                            </linearGradient>
                            <linearGradient id="sideDialGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#34D399" />
                                <stop offset="100%" stop-color="#60A5FA" />
                            </linearGradient>
                        </defs>
                        <rect x="3" y="4" width="18" height="16" rx="3.5" stroke="url(#sideVaultGrad)" stroke-width="2.5" />
                        <circle cx="12" cy="12" r="3.8" stroke="url(#sideDialGrad)" stroke-width="1.8" />
                        <circle cx="12" cy="12" r="1.2" fill="url(#sideVaultGrad)" />
                        <path d="M12 6v1M12 17v1M6 12h1M17 12h1" stroke="url(#sideDialGrad)" stroke-width="1.2" stroke-linecap="round" />
                    </svg>
                    <span class="text-lg font-black text-white tracking-tight">CashBox</span>
                </Link>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-1.5 px-4 py-6 overflow-y-auto">
                <!-- Dashboard Link -->
                <Link
                    :href="route('dashboard')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('dashboard')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span>Dashboard</span>
                </Link>


                <!-- Budget Targets Link -->
                <Link
                    v-if="hasModulePermission('budgets')"
                    :href="route('budgets.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('budgets.index')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    <span>Budget Targets</span>
                </Link>

                <!-- AI Budget Monitor Link -->
                <Link
                    v-if="hasModulePermission('budgets')"
                    :href="route('budgets.monitor')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('budgets.monitor')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>AI Budget Monitor</span>
                </Link>


                <!-- My Accounts Link -->
                <Link
                    :href="route('accounts.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('accounts.index')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>My Accounts</span>
                </Link>

                <!-- Transactions Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('transactions.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('transactions.index')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span>Transactions</span>
                </Link>

                <!-- Categories Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('categories.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('categories.index')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Categories</span>
                </Link>

                <!-- Reports Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('reports.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('reports.index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Reports & Projections</span>
                </Link>

                <!-- Daily Report Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('reports.daily')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('reports.daily')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Daily Report</span>
                </Link>

                <!-- Monthly Report Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('reports.monthly')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('reports.monthly')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Monthly Report</span>
                </Link>

                <!-- Forecast Link -->
                <Link
                    v-if="hasModulePermission('ledger')"
                    :href="route('reports.forecast')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('reports.forecast')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Forecast</span>
                </Link>

                <!-- SaaS Licenses Link -->
                <Link
                    v-if="hasModulePermission('licenses')"
                    :href="route('licenses.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('licenses.index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m-2 4a2 2 0 012 2m-2-4a2 2 0 11-4 0m8 8a2 2 0 01-2-2V5a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2zM4 14h.01M4 18h.01" />
                    </svg>
                    <span>SaaS Licenses</span>
                </Link>

                <!-- Loans & Debts Link -->
                <Link
                    v-if="hasModulePermission('loans')"
                    :href="route('loans.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('loans.index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Loans & Debts</span>
                </Link>

                <!-- Recurring Items Link -->
                <Link
                    v-if="hasModulePermission('recurring')"
                    :href="route('recurring.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('recurring.index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Recurring Items</span>
                </Link>

                <!-- Superadmin Panel Link -->
                <Link
                    v-if="$page.props.auth.user.is_superadmin"
                    :href="route('superadmin.index')"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-150',
                        route().current('superadmin.index')
                            ? 'bg-indigo-650 text-white shadow-md shadow-indigo-500/10'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>Superadmin Panel</span>
                </Link>
            </nav>

            <!-- Bottom User Profile Area in Sidebar -->
            <div class="border-t border-slate-800 p-4 shrink-0 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-indigo-600/20 text-indigo-405 font-extrabold text-xs flex items-center justify-center uppercase tracking-wider shrink-0">
                            {{ $page.props.auth.user.name.substring(0, 2) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-black text-white truncate">{{ $page.props.auth.user.name }}</p>
                            <p class="text-[9px] text-slate-500 truncate mt-0.5">{{ $page.props.auth.user.email }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link 
                        :href="route('profile.edit')"
                        class="flex-1 py-1.5 px-3 bg-slate-800 hover:bg-slate-750 hover:text-white text-slate-350 rounded-lg text-center font-bold text-[10px] uppercase tracking-wider transition"
                    >
                        Profile
                    </Link>
                    <Link 
                        :href="route('logout')" 
                        method="post" 
                        as="button"
                        class="flex-1 py-1.5 px-3 bg-red-950/20 hover:bg-red-900/30 text-red-400 rounded-lg text-center font-bold text-[10px] uppercase tracking-wider transition border border-red-900/50"
                    >
                        Log Out
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Panel Content -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden lg:pl-64">
            <!-- Header/Top Navigation Bar -->
            <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 h-16 flex items-center justify-between px-6 shrink-0 z-30 shadow-sm">
                <div class="flex items-center gap-3">
                    <!-- Hamburger / Toggle button for mobile -->
                    <button 
                        @click="isSidebarOpen = !isSidebarOpen"
                        class="p-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 rounded-lg lg:hidden focus:outline-none"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Mobile Brand Logo/Title -->
                    <div class="flex items-center gap-2 lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24">
                            <defs>
                                <linearGradient id="mobVaultGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#818CF8" />
                                    <stop offset="50%" stop-color="#C084FC" />
                                    <stop offset="100%" stop-color="#FB7185" />
                                </linearGradient>
                                <linearGradient id="mobDialGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#34D399" />
                                    <stop offset="100%" stop-color="#60A5FA" />
                                </linearGradient>
                            </defs>
                            <rect x="3" y="4" width="18" height="16" rx="3.5" stroke="url(#mobVaultGrad)" stroke-width="2.5" />
                            <circle cx="12" cy="12" r="3.8" stroke="url(#mobDialGrad)" stroke-width="1.8" />
                            <circle cx="12" cy="12" r="1.2" fill="url(#mobVaultGrad)" />
                            <path d="M12 6v1M12 17v1M6 12h1M17 12h1" stroke="url(#mobDialGrad)" stroke-width="1.2" stroke-linecap="round" />
                        </svg>
                        <span class="text-base font-black text-slate-900 dark:text-white tracking-tight">Cashbox</span>
                    </div>
                </div>

                <!-- Top Right controls -->
                <div class="flex items-center gap-3">
                    <!-- Push Notification Toggle Button -->
                    <button
                        v-if="firebaseConfig && firebaseConfig.apiKey"
                        @click="requestNotificationPermission"
                        :disabled="isSubscribing"
                        class="p-2 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none transition relative group rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
                        :class="{
                            'text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-950/20': notificationPermission === 'granted',
                            'text-amber-500 border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-950/20': notificationPermission === 'default',
                            'text-gray-400 opacity-60 cursor-not-allowed': notificationPermission === 'denied'
                        }"
                        :title="
                            notificationPermission === 'granted'
                                ? 'Reminders push notifications active'
                                : notificationPermission === 'default'
                                    ? 'Click to enable reminders push notifications'
                                    : 'Notifications are blocked in your browser settings'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            :class="{ 'animate-bounce': notificationPermission === 'default' && !isSubscribing }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                            <path
                                v-if="notificationPermission === 'denied'"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 3l18 18"
                                class="stroke-red-500"
                            />
                        </svg>
                        
                        <span class="absolute top-full mt-2 right-0 hidden group-hover:block bg-slate-900 dark:bg-slate-800 text-white text-[10px] py-1 px-2 rounded whitespace-nowrap z-50 shadow-md">
                            {{ 
                                notificationPermission === 'granted' 
                                    ? 'Push Notifications: Active' 
                                    : notificationPermission === 'default' 
                                        ? 'Enable Push Notifications' 
                                        : 'Notifications Blocked' 
                            }}
                        </span>
                    </button>

                    <!-- Settings Dropdown -->
                    <div class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <span class="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition duration-150"
                                    >
                                        {{ $page.props.auth.user.name }}
                                        <svg
                                            class="-me-0.5 ms-2 h-4 w-4 text-slate-400"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto min-h-0 bg-slate-50 dark:bg-slate-950 flex flex-col justify-between">
                <div>
                    <!-- Page Heading (rendered cleanly below header bar with full width spacing) -->
                    <div v-if="$slots.header" class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 py-5 px-6 md:px-8">
                        <div class="mx-auto max-w-7xl">
                            <slot name="header" />
                        </div>
                    </div>

                    <!-- Main Content Slot -->
                    <slot />
                </div>

                <!-- Footer Section -->
                <footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-4 px-6 md:px-8 mt-8">
                    <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-1.5 font-semibold">
                            <span class="font-extrabold text-indigo-600 dark:text-indigo-400">CashBox</span>
                            <span>&copy; 2026. All rights reserved.</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[10px] text-slate-400 dark:text-slate-500">
                            <span>Developed by: <strong class="text-slate-650 dark:text-slate-350">PRANTIK-SOFT</strong></span>
                            <span class="hidden sm:inline">|</span>
                            <span>Version: <strong class="text-slate-650 dark:text-slate-350">1.2.0</strong></span>
                        </div>
                    </div>
                </footer>
            </main>
        </div>
    </div>
</template>
