<div>
    <a href="{{ route('customers.index') }}" class="text-sm font-medium text-teal-800">← Clientes</a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="page-title break-words">{{ $customer->name }}</h1><p class="mt-2 text-sm text-stone-500">{{ $customer->is_active ? 'Cliente ativa' : 'Cliente inativa' }} · Cadastrada em {{ $customer->created_at->timezone(app(\App\Modules\Platform\CurrentTenant::class)->get()->timezone)->format('d/m/Y') }}</p></div>
        <a href="{{ route('customers.edit', $customer->id) }}" class="primary-button"><x-icon name="edit" /> <span>Editar cadastro</span></a>
    </div>
    <x-flash />
    <section class="mt-7 rounded-2xl border border-stone-200 bg-white p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Dados da cliente</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-stone-500">Telefone</dt><dd class="mt-1">{{ $customer->phone }}</dd></div><div class="min-w-0"><dt class="text-sm text-stone-500">E-mail</dt><dd class="mt-1 break-words">{{ $customer->email ?: 'Não informado' }}</dd></div></dl>
        @if($customer->notes)<h3 class="mt-5 text-sm text-stone-500">Observações</h3><p class="mt-1 whitespace-pre-line break-words">{{ $customer->notes }}</p>@endif
        <button wire:click="toggleActive" data-confirm="{{ $customer->is_active ? 'Desativar esta cliente? Seus dados serão preservados.' : 'Reativar esta cliente?' }}" wire:loading.attr="disabled" class="mt-6 min-h-11 text-sm font-medium text-teal-800 underline underline-offset-4">{{ $customer->is_active ? 'Desativar cliente' : 'Reativar cliente' }}</button>
    </section>
    @include('livewire.customer.operational-history')
    <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Endereços de atendimento</h2><a href="{{ route('customers.addresses.create', $customer->id) }}" class="min-h-11 py-3 text-sm font-semibold text-teal-800"><x-icon name="pin" /> <span>Adicionar endereço</span></a></div>
        <div class="mt-4 space-y-4">
            @forelse($customer->addresses as $address)
                <article wire:key="address-{{ $address->id }}" class="rounded-xl border border-stone-200 p-4">
                    @if($address->is_primary)<span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-800">Principal</span>@endif
                    <p class="mt-2 break-words">{{ $address->formatted }}</p>
                    @if($address->latitude !== null && $address->longitude !== null)
                        <p class="mt-2 text-xs text-stone-500">Coordenadas do Google: latitude {{ $address->latitude }} · longitude {{ $address->longitude }}</p>
                    @elseif($address->geocoding_warning)
                        <p role="status" class="mt-2 text-sm text-amber-800">{{ $address->geocoding_warning }}</p>
                    @else
                        <p class="mt-2 text-xs text-stone-500">Coordenadas ainda não consultadas. Edite e salve o endereço para localizá-lo.</p>
                    @endif
                    <x-uber-link :address="$address->formatted" :latitude="$address->latitude" :longitude="$address->longitude" />
                    @if($address->reference)<p class="mt-2 text-sm text-stone-500">Referência: {{ $address->reference }}</p>@endif
                    <div class="mt-2 flex flex-wrap gap-5 text-sm font-medium text-teal-800"><a href="{{ $address->maps_url }}" target="_blank" rel="noopener noreferrer" class="py-3"><x-icon name="pin" /> <span>Abrir no Google Maps ↗</span></a><a href="{{ route('customers.addresses.edit', [$customer->id, $address->id]) }}" class="py-3"><x-icon name="edit" /> <span>Editar</span></a><button wire:click="removeAddress({{ $address->id }})" data-confirm="Remover este endereço do cadastro?" wire:loading.attr="disabled" class="py-3 text-stone-600"><x-icon name="trash" /> <span>Remover</span></button></div>
                </article>
            @empty
                <p class="py-3 text-sm text-stone-500">Nenhum endereço cadastrado. Adicione o local onde a cliente será atendida.</p>
            @endforelse
        </div>
    </section>
    <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Preços personalizados</h2>
        <p class="mt-2 text-sm leading-relaxed text-stone-500">Defina o preço habitual de um procedimento para esta cliente. Ele terá prioridade sobre o preço padrão na sugestão de novas contratações.</p>
        <div class="mt-5 space-y-3">
            @forelse($customer->procedurePrices as $customPrice)
                <div wire:key="price-{{ $customPrice->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50 p-4"><div><h3 class="font-medium">{{ $customPrice->procedure->name }} @if(!$customPrice->procedure->is_active)<span class="text-xs text-stone-500">(inativo)</span>@endif</h3><p class="mt-1 text-sm">{{ \App\Modules\Catalog\DecimalAmount::format($customPrice->price) }} <span class="text-stone-500">· Padrão: {{ \App\Modules\Catalog\DecimalAmount::format($customPrice->procedure->default_price) }}</span></p></div><button wire:click="removePrice({{ $customPrice->id }})" data-confirm="Remover o preço personalizado e voltar a sugerir o preço padrão?" wire:loading.attr="disabled" class="min-h-11 text-sm text-stone-600 underline"><x-icon name="trash" /> <span>Remover</span></button></div>
            @empty
                <p class="text-sm text-stone-500">Esta cliente utiliza os preços padrão do catálogo.</p>
            @endforelse
        </div>
        @if($procedures->isNotEmpty())
            <form wire:submit="savePrice" class="mt-6 grid items-end gap-4 sm:grid-cols-[1fr_10rem_auto]">
                <div><label for="procedureId" class="field-label">Procedimento</label><x-form.select id="procedureId" wire:model="procedureId" required class="field-input"><option value="">Selecione</option>@foreach($procedures as $procedure)<option value="{{ $procedure->id }}">{{ $procedure->name }}{{ $procedure->is_active ? '' : ' (inativo)' }}</option>@endforeach</x-form.select>@error('procedureId')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <x-form.input name="price" label="Preço (R$)" wire:model="price" inputmode="decimal" placeholder="120,00" required />
                <button class="primary-button" wire:loading.attr="disabled"><x-icon name="save" /> <span>Salvar preço</span></button>
            </form>
            <p class="mt-3 text-xs text-stone-500">Use vírgula ou ponto para os centavos, sem separador de milhar. Salvar novamente o mesmo procedimento atualiza o preço habitual.</p>
        @else
            <a href="{{ route('procedures.create') }}" class="mt-5 inline-block py-3 text-sm font-semibold text-teal-800">Cadastrar primeiro procedimento →</a>
        @endif
    </section>
</div>
