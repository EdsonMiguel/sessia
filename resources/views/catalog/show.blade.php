<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Conheça os serviços de {{ $business->name }} e peça mais informações pelo WhatsApp.">
    <title>{{ $business->name }} · Catálogo de serviços</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-[#faf8f4] text-stone-800 antialiased">
    <header class="border-b border-stone-200/70">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <a href="{{ route('catalog.show', $business->slug) }}" class="flex min-w-0 items-center gap-3 font-semibold tracking-tight text-teal-950">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-900 text-white"><x-icon name="sparkles" /></span>
                <span class="break-words">{{ $business->name }}</span>
            </a>
            <a href="#servicos" class="shrink-0 rounded-full border border-stone-300 px-4 py-3 text-sm font-medium hover:border-teal-800">Ver serviços</a>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-5 sm:px-8">
        <section class="grid items-center gap-8 py-12 sm:py-16 lg:grid-cols-[1.4fr_1fr] lg:gap-16 lg:py-20">
            <div>
                <p class="eyebrow">Um tempo para você</p>
                <h1 class="mt-5 max-w-2xl text-4xl font-medium leading-tight tracking-tight text-teal-950 sm:text-6xl">Seu cuidado começa<br>com uma escolha.</h1>
                <p class="mt-6 max-w-xl text-base leading-relaxed text-stone-600 sm:text-lg">Conheça os serviços de {{ $business->name }}, escolha o que combina com você e converse sobre os detalhes pelo WhatsApp.</p>
                <a href="#servicos" class="mt-7 inline-flex min-h-12 items-center gap-3 rounded-full bg-teal-900 px-6 py-3 text-sm font-semibold text-white hover:bg-teal-800">Explorar o catálogo <x-icon name="arrow-right" /></a>
            </div>
            <aside class="relative overflow-hidden rounded-[2rem] bg-[#e7eee7] p-7 sm:p-10">
                <div aria-hidden="true" class="absolute -right-12 -top-12 size-44 rounded-full border-[24px] border-white/40"></div>
                <x-icon name="sparkles" class="relative size-10 text-teal-800" />
                <h2 class="relative mt-6 text-2xl font-medium tracking-tight text-teal-950">Escolha com calma.<br>Converse com quem cuida.</h2>
                <p class="relative mt-4 text-sm leading-relaxed text-teal-950/75">Veja os detalhes de cada serviço e tire suas dúvidas diretamente com o negócio antes de agendar.</p>
            </aside>
        </section>

        <section id="servicos" class="scroll-mt-6 border-t border-stone-200 pt-9">
            <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div><p class="eyebrow">Catálogo</p><h2 class="mt-2 text-3xl font-medium tracking-tight text-teal-950">Encontre o seu próximo cuidado</h2></div>
                <p class="text-sm text-stone-500">{{ $procedures->total() }} {{ $procedures->total() === 1 ? 'serviço disponível' : 'serviços disponíveis' }}</p>
            </div>
            @if($procedures->isNotEmpty())
                <form action="{{ route('catalog.contact', $business->slug) }}" method="GET">
                    <fieldset>
                        <legend class="mb-5 text-sm text-stone-600">Selecione um serviço para pedir mais informações.</legend>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($procedures as $procedure)
                                <label class="block cursor-pointer">
                                    <input type="radio" name="procedure" value="{{ $procedure->id }}" required class="peer sr-only" aria-label="Selecionar {{ $procedure->name }}">
                                    <div class="flex h-full flex-col rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-teal-500 peer-checked:border-teal-800 peer-checked:bg-teal-50 peer-checked:ring-2 peer-checked:ring-teal-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-teal-700 sm:p-6">
                                        <div class="mb-5 flex items-center justify-between gap-3">
                                            <span class="flex size-11 items-center justify-center rounded-xl bg-stone-100 text-teal-800"><x-icon name="sparkles" /></span>
                                            <span class="inline-flex items-center gap-1.5 text-xs text-stone-500"><x-icon name="clock" class="size-4" /> {{ $procedure->duration_minutes }} min</span>
                                        </div>
                                        <h3 class="text-xl font-semibold tracking-tight text-teal-950">{{ $procedure->name }}</h3>
                                        @if($procedure->description)<p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $procedure->description }}</p>@endif
                                        <div class="mt-auto pt-6">
                                            <p class="text-xs text-stone-500">Preço de referência</p>
                                            <p class="mt-1 text-2xl font-semibold text-teal-900">{{ \App\Modules\Catalog\DecimalAmount::format($procedure->default_price) }}</p>
                                            <span class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-800"><x-icon name="check" /> Selecionar serviço</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="my-6">{{ $procedures->links() }}</div>
                    <div class="sticky bottom-0 z-10 mt-8 flex flex-wrap items-center justify-between gap-3 rounded-t-2xl border border-stone-200 bg-[#faf8f4] p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-lg sm:rounded-2xl sm:p-5">
                        <p class="text-sm text-stone-600">Gostou de um serviço? Vamos conversar.</p>
                        @if($business->phone)
                            <button class="primary-button"><x-icon name="chat" /> Pedir informações no WhatsApp</button>
                        @else
                            <p class="text-sm text-stone-500">Contato pelo WhatsApp ainda não disponível.</p>
                        @endif
                    </div>
                </form>
            @elseif($packages->total() === 0)
                <div class="rounded-2xl border border-dashed border-stone-300 p-8 text-center sm:p-12"><x-icon name="sparkles" class="size-8 text-teal-700" /><h3 class="mt-4 text-lg font-semibold">Novos cuidados em breve</h3><p class="mt-2 text-sm text-stone-500">Este negócio ainda não publicou serviços no catálogo.</p></div>
            @endif
            @if($packages->total() > 0)
                <section id="pacotes" class="mt-10 border-t border-stone-200 pt-8" aria-labelledby="packages-title">
                    <div class="mb-6"><p class="eyebrow">Cuidados que se completam</p><h2 id="packages-title" class="mt-2 text-3xl font-medium tracking-tight text-teal-950">Pacotes de procedimentos</h2><p class="mt-3 text-sm text-stone-600">{{ $packages->total() }} {{ $packages->total() === 1 ? 'pacote disponível' : 'pacotes disponíveis' }}. Escolha uma combinação e saiba mais.</p></div>
                    <form action="{{ route('catalog.contact', $business->slug) }}" method="GET">
                        <fieldset>
                            <legend class="mb-5 text-sm text-stone-600">Selecione um pacote para conversar sobre ele.</legend>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                @foreach($packages as $package)
                                    <label class="block min-w-0 cursor-pointer">
                                        <input type="radio" name="package" value="{{ $package->id }}" required class="peer sr-only" aria-label="Selecionar pacote {{ $package->name }}">
                                        <div class="flex h-full flex-col rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-teal-500 peer-checked:border-teal-800 peer-checked:bg-teal-50 peer-checked:ring-2 peer-checked:ring-teal-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-teal-700 sm:p-6">
                                            <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-teal-50 text-teal-800"><x-icon name="package" /></span>
                                            <h3 class="break-words text-xl font-semibold tracking-tight text-teal-950">{{ $package->name }}</h3>
                                            @if($package->description)<p class="mt-3 whitespace-pre-line break-words text-sm leading-relaxed text-stone-600">{{ $package->description }}</p>@endif
                                            <ul class="mt-5 space-y-2 text-sm text-stone-600">@foreach($package->items as $item)<li class="flex items-start gap-2"><x-icon name="check" class="size-4 text-teal-700" /><span class="min-w-0 break-words">{{ $item->quantity }} {{ $item->quantity === 1 ? 'sessão' : 'sessões' }} de {{ $item->procedure->name }}</span></li>@endforeach</ul>
                                            <div class="mt-auto pt-6"><p class="text-xs text-stone-500">Valor do pacote</p><p class="mt-1 text-2xl font-semibold text-teal-900">{{ \App\Modules\Catalog\DecimalAmount::format($package->total) }}</p><span class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-800"><x-icon name="check" /> Selecionar pacote</span></div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="my-6">{{ $packages->fragment('pacotes')->links() }}</div>
                        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:p-5">
                            <p class="text-sm text-stone-600">Converse sobre o pacote escolhido.</p>
                            @if($business->phone)<button class="primary-button"><x-icon name="chat" /> Saber mais sobre o pacote</button>@else<p class="text-sm text-stone-500">Contato pelo WhatsApp ainda não disponível.</p>@endif
                        </div>
                    </form>
                </section>
            @endif
            <p class="mt-6 text-center text-xs leading-relaxed text-stone-500">Valores de referência. Confirme condições e disponibilidade diretamente com o negócio. O contato não confirma um agendamento.</p>
        </section>
    </main>
    <footer class="mx-auto mt-12 flex max-w-6xl flex-wrap items-center justify-between gap-3 border-t border-stone-200 px-5 py-7 text-xs text-stone-500 sm:px-8"><p>{{ $business->name }}</p><p>Catálogo por <span class="font-semibold text-teal-900">sessia.</span></p></footer>
</body>
</html>
