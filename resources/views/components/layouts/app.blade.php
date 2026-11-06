@php($currentBusiness = app(\App\Modules\Platform\CurrentTenant::class)->get())
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sessia · {{ $currentBusiness->name }}</title>
    <x-pwa-meta />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <div class="lg:flex lg:min-h-screen">
        <aside x-data="{ menuOpen: false }" x-on:keydown.escape="menuOpen = false; $refs.menuToggle.focus()" class="app-sidebar sticky top-0 z-30 border-b border-stone-200 bg-white px-4 py-3 lg:h-dvh lg:w-64 lg:shrink-0 lg:overflow-y-auto lg:border-r lg:p-7">
            <div class="flex min-w-0 items-center justify-between gap-4">
                <div class="min-w-0">
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center text-3xl font-semibold tracking-tight text-teal-900">sessia<span class="text-orange-600">.</span></a>
                    <p class="truncate text-xs text-stone-500 lg:mt-2 lg:text-sm">{{ $currentBusiness->name }}</p>
                </div>
                <button type="button" x-ref="menuToggle" x-on:click="menuOpen = !menuOpen" x-bind:aria-expanded="menuOpen.toString()" aria-expanded="false" aria-controls="main-navigation" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border border-stone-200 px-4 text-sm font-semibold text-teal-900 lg:hidden">
                    <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <span x-text="menuOpen ? 'Fechar' : 'Menu'">Menu</span>
                </button>
            </div>
            <div id="main-navigation" class="navigation-panel" x-bind:data-open="menuOpen">
            <nav aria-label="Menu principal" class="mt-4 flex flex-col gap-1 lg:mt-6 lg:gap-2">
                <a href="{{ route('dashboard') }}" @class(['nav-link', 'nav-active' => request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="home" /> <span>Início</span></a>
                <a href="{{ route('agenda') }}" @class(['nav-link', 'nav-active' => request()->routeIs('agenda', 'appointments.*')]) @if(request()->routeIs('agenda', 'appointments.*')) aria-current="page" @endif><x-icon name="calendar" /> <span>Agenda</span></a>
                <a href="{{ route('customers.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('customers.*')]) @if(request()->routeIs('customers.*')) aria-current="page" @endif><x-icon name="users" /> <span>Clientes</span></a>
                <a href="{{ route('procedures.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('procedures.*')]) @if(request()->routeIs('procedures.*')) aria-current="page" @endif><x-icon name="sparkles" /> <span>Procedimentos</span></a>
                <a href="{{ route('packages.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('packages.*')]) @if(request()->routeIs('packages.*')) aria-current="page" @endif><x-icon name="package" /> <span>Pacotes</span></a>
                <a href="{{ route('contracts.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('contracts.*')]) @if(request()->routeIs('contracts.*')) aria-current="page" @endif><x-icon name="document" /> <span>Contratações</span></a>
                <a href="{{ route('billing.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('billing.*')]) @if(request()->routeIs('billing.*')) aria-current="page" @endif><x-icon name="wallet" /> <span>Financeiro</span></a>
                <a href="{{ route('business.settings') }}" @class(['nav-link', 'nav-active' => request()->routeIs('business.settings')]) @if(request()->routeIs('business.settings')) aria-current="page" @endif><x-icon name="settings" /> <span>Configurações do negócio</span></a>
                <a href="{{ route('account.show') }}" @class(['nav-link', 'nav-active' => request()->routeIs('account.show')]) @if(request()->routeIs('account.show')) aria-current="page" @endif><x-icon name="lock" /> <span>Minha conta</span></a>
            </nav>
            <form action="{{ route('logout') }}" method="POST" class="mt-6 border-t border-stone-100 pt-4">
                @csrf
                <p class="mb-3 truncate text-sm text-stone-600">{{ auth()->user()->name }}</p>
                <button class="min-h-11 text-sm font-medium text-stone-600 hover:text-teal-800"><x-icon name="logout" /> <span>Sair da conta</span></button>
            </form>
            </div>
        </aside>
        <main class="app-content mx-auto min-w-0 w-full max-w-6xl px-4 py-6 sm:p-8 lg:p-12" id="main-content">{{ $slot }}</main>
    </div>
    <x-confirmation-dialog />
    @livewireScriptConfig
</body>
</html>
