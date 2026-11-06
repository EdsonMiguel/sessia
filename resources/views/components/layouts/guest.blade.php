<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sessia · Seu negócio, com cuidado</title>
    <x-pwa-meta />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <main class="mx-auto grid min-h-dvh w-full max-w-6xl content-start items-center gap-6 px-4 py-6 sm:px-6 sm:py-10 lg:content-center lg:grid-cols-2 lg:gap-24">
        <section>
            <a href="{{ route('login') }}" class="text-3xl font-semibold tracking-tight text-teal-900">sessia<span class="text-orange-600">.</span></a>
            <p class="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-teal-700 lg:mt-16">Mais leveza para o seu dia</p>
            <h1 class="mt-4 max-w-md text-2xl font-medium leading-tight tracking-tight sm:text-3xl lg:mt-5 lg:text-5xl">Você cuida de pessoas.<br><span class="text-teal-700">A gente organiza o resto.</span></h1>
            <p class="mt-6 hidden max-w-md leading-relaxed text-stone-600 lg:block">Um espaço para organizar seu negócio de estética, com a simplicidade que a sua rotina precisa.</p>
            <p class="mt-10 hidden text-sm text-stone-500 lg:block">Feito para acompanhar você, onde estiver.</p>
        </section>
        <section class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-10" aria-label="Acesso ao Sessia">{{ $slot }}</section>
    </main>
    @livewireScriptConfig
</body>
</html>
