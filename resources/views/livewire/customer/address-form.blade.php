<div>
    <a href="{{ route('customers.show', $customerId) }}" class="text-sm font-medium text-teal-800">← {{ $customer->name }}</a>
    <h1 class="page-title">{{ $addressId ? 'Editar endereço' : 'Novo endereço' }}</h1>
    <p class="mt-3 text-stone-500">Onde será o atendimento?</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-5 rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
        <x-form.input name="postal_code" label="CEP" wire:model="postal_code" required maxlength="9" inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" />
        <x-form.input name="street" label="Logradouro" wire:model="street" required maxlength="160" autocomplete="address-line1" />
        <div class="grid gap-5 sm:grid-cols-2"><x-form.input name="number" label="Número" wire:model="number" required maxlength="20" placeholder="Número ou s/n" /><x-form.input name="complement" label="Complemento (opcional)" wire:model="complement" maxlength="120" autocomplete="address-line2" /></div>
        <x-form.input name="neighborhood" label="Bairro" wire:model="neighborhood" required maxlength="120" />
        <div class="grid gap-5 sm:grid-cols-[1fr_8rem]"><x-form.input name="city" label="Cidade" wire:model="city" required maxlength="120" autocomplete="address-level2" /><div><label for="state" class="field-label">Estado</label><x-form.select id="state" wire:model="state" required class="field-input" autocomplete="address-level1"><option value="">UF</option>@foreach(\App\Livewire\Customer\AddressForm::STATES as $uf)<option value="{{ $uf }}">{{ $uf }}</option>@endforeach</x-form.select>@error('state')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div></div>
        <x-form.input name="reference" label="Ponto de referência (opcional)" wire:model="reference" maxlength="255" />
        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="is_primary" class="size-4 accent-teal-800"> Usar como endereço principal</label>
        <div class="flex flex-wrap items-center gap-4"><button class="primary-button" wire:loading.attr="disabled"><x-icon name="save" /> <span>Salvar endereço</span></button><a href="{{ route('customers.show', $customerId) }}" class="text-sm text-stone-600"><x-icon name="close" /> <span>Cancelar</span></a></div>
    </form>
</div>
