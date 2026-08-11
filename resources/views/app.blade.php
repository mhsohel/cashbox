<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'CashBox') }}</title>

        <!-- SEO Metadata & OpenGraph Tags -->
        <meta name="description" content="The Smart Multi-Account Budget, Debt & SaaS Billing OS for Indie Hackers. Consolidate cash flows across bank accounts, credit cards, and mobile wallets.">
        <meta property="og:title" content="Cashbox - Premium Multi-Account Budget, Loans & SaaS Revenue OS">
        <meta property="og:description" content="Consolidate cash flows across bank accounts and mobile wallets. Track daily budgets, log outstanding loans, process recurring bill reminders, and manage client SaaS licensing revenues.">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:type" content="website">
        
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Cashbox - Premium Multi-Account Budget, Loans & SaaS Revenue OS">
        <meta name="twitter:description" content="Consolidate cash flows across bank accounts and mobile wallets. Track daily budgets, log outstanding loans, process recurring bill reminders, and manage client SaaS licensing revenues.">
        <meta name="twitter:image" content="{{ asset('og-image.png') }}">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
