@php($currentBusiness = app(\App\Modules\Platform\CurrentTenant::class)->get())
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sessia · {{ $currentBusiness->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <div class="lg:flex lg:min-h-screen">
        <aside class="border-b border-stone-200 bg-white p-5 lg:w-64 lg:shrink-0 lg:border-r lg:p-7">
            <a href="{{ route('dashboard') }}" class="text-3xl font-semibold tracking-tight text-teal-900">sessia<span class="text-orange-600">.</span></a>
            <p class="mt-2 truncate text-sm text-stone-500">{{ $currentBusiness->name }}</p>
            <nav aria-label="Menu principal" class="mt-6 flex flex-wrap gap-2 lg:flex-col">
                <a href="{{ route('dashboard') }}" @class(['nav-link', 'nav-active' => request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif>Início</a>
                <a href="{{ route('agenda') }}" @class(['nav-link', 'nav-active' => request()->routeIs('agenda', 'appointments.*')]) @if(request()->routeIs('agenda', 'appointments.*')) aria-current="page" @endif>Agenda</a>
                <a href="{{ route('customers.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('customers.*')]) @if(request()->routeIs('customers.*')) aria-current="page" @endif>Clientes</a>
                <a href="{{ route('procedures.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('procedures.*')]) @if(request()->routeIs('procedures.*')) aria-current="page" @endif>Procedimentos</a>
                <a href="{{ route('contracts.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('contracts.*')]) @if(request()->routeIs('contracts.*')) aria-current="page" @endif>Contratações</a>
                <a href="{{ route('billing.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('billing.*')]) @if(request()->routeIs('billing.*')) aria-current="page" @endif>Financeiro</a>
                <a href="{{ route('business.settings') }}" @class(['nav-link', 'nav-active' => request()->routeIs('business.settings')]) @if(request()->routeIs('business.settings')) aria-current="page" @endif>Configurações do negócio</a>
            </nav>
            <form action="{{ route('logout') }}" method="POST" class="mt-6 border-t border-stone-100 pt-4">
                @csrf
                <p class="mb-3 truncate text-sm text-stone-600">{{ auth()->user()->name }}</p>
                <button class="text-sm font-medium text-stone-600 hover:text-teal-800">Sair da conta</button>
            </form>
        </aside>
        <main class="mx-auto min-w-0 w-full max-w-6xl p-5 sm:p-8 lg:p-12" id="main-content">{{ $slot }}</main>
    </div>
    @livewireScripts
</body>
</html>
