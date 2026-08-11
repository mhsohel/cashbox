<template>
    <div class="flex items-center gap-2.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 shrink-0" fill="none" viewBox="0 0 24 24">
            <defs>
                <linearGradient id="appVaultGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#818CF8" />
                    <stop offset="50%" stop-color="#C084FC" />
                    <stop offset="100%" stop-color="#FB7185" />
                </linearGradient>
                <linearGradient id="appDialGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#34D399" />
                    <stop offset="100%" stop-color="#60A5FA" />
                </linearGradient>
            </defs>
            <rect x="3" y="4" width="18" height="16" rx="3.5" stroke="url(#appVaultGrad)" stroke-width="2.5" />
            <circle cx="12" cy="12" r="3.8" stroke="url(#appDialGrad)" stroke-width="1.8" />
            <circle cx="12" cy="12" r="1.2" fill="url(#appVaultGrad)" />
            <path d="M12 6v1M12 17v1M6 12h1M17 12h1" stroke="url(#appDialGrad)" stroke-width="1.2" stroke-linecap="round" />
        </svg>
        <span class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">CashBox</span>
    </div>
</template>
