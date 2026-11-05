<div>
    <a href="{{ $customerId ? route('customers.show', $customerId) : route('customers.index') }}" class="text-sm font-medium text-teal-800">← Voltar</a>
    <h1 class="page-title">{{ $customerId ? 'Editar cliente' : 'Nova cliente' }}</h1>
    <p class="mt-3 text-stone-500">Os dados essenciais para manter o cuidado por perto.</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-5 rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
        <x-form.input name="name" label="Nome completo" wire:model="name" required maxlength="120" autocomplete="name" />
        <x-form.input name="phone" label="Telefone" wire:model="phone" type="tel" required maxlength="30" autocomplete="tel" />
        <x-form.input name="email" label="E-mail (opcional)" wire:model="email" type="email" maxlength="255" autocomplete="email" />
        <x-form.textarea name="notes" label="Observações (opcional)" wire:model="notes" maxlength="5000" />
        <p class="text-xs text-stone-500">Use as observações para informações da rotina de atendimento. Não inclua dados de saúde.</p>
        <fieldset class="space-y-5 border-t border-stone-200 pt-5">
            <legend class="px-1 text-lg font-semibold text-stone-900">Endereço principal</legend>
            <p class="text-sm text-stone-500">Você pode cadastrar o endereço agora ou depois. Ao preencher, informe CEP, logradouro, número, bairro, cidade e estado.</p>
            <x-form.input name="postal_code" label="CEP" wire:model="postal_code" maxlength="9" inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" />
            <x-form.input name="street" label="Logradouro" wire:model="street" maxlength="160" autocomplete="address-line1" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="number" label="Número" wire:model="number" maxlength="20" placeholder="Número ou s/n" />
                <x-form.input name="complement" label="Complemento (opcional)" wire:model="complement" maxlength="120" autocomplete="address-line2" />
            </div>
            <x-form.input name="neighborhood" label="Bairro" wire:model="neighborhood" maxlength="120" />
            <div class="grid gap-5 sm:grid-cols-[1fr_8rem]">
                <x-form.input name="city" label="Cidade" wire:model="city" maxlength="120" autocomplete="address-level2" />
                <div>
                    <label for="state" class="field-label">Estado</label>
                    <select id="state" wire:model="state" class="field-input" autocomplete="address-level1">
                        <option value="">UF</option>
                        @foreach(\App\Livewire\Customer\AddressForm::STATES as $uf)
                            <option value="{{ $uf }}">{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('state')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-form.input name="reference" label="Ponto de referência (opcional)" wire:model="reference" maxlength="255" />
        </fieldset>
        <div class="flex flex-wrap items-center gap-4"><button class="primary-button" wire:loading.attr="disabled"><x-icon name="save" /> <span>Salvar cliente</span></button><a href="{{ $customerId ? route('customers.show', $customerId) : route('customers.index') }}" class="text-sm text-stone-600"><x-icon name="close" /> <span>Cancelar</span></a></div>
    </form>
</div>
