<div>
    <a href="{{ $customerId ? route('customers.show', $customerId) : route('customers.index') }}" class="text-sm font-medium text-teal-800">← Voltar</a>
    <h1 class="page-title">{{ $customerId ? 'Editar cliente' : 'Nova cliente' }}</h1>
    <p class="mt-3 text-stone-500">Os dados essenciais para manter o cuidado por perto.</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-5 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        <x-form.input name="name" label="Nome completo" wire:model="name" required maxlength="120" autocomplete="name" />
        <x-form.input name="phone" label="Telefone" wire:model="phone" type="tel" required maxlength="30" autocomplete="tel" />
        <x-form.input name="email" label="E-mail (opcional)" wire:model="email" type="email" maxlength="255" autocomplete="email" />
        <x-form.textarea name="notes" label="Observações (opcional)" wire:model="notes" maxlength="5000" />
        <p class="text-xs text-stone-500">Use as observações para informações da rotina de atendimento. Não inclua dados de saúde.</p>
        <div class="flex flex-wrap items-center gap-4"><button class="primary-button" wire:loading.attr="disabled">Salvar cliente</button><a href="{{ $customerId ? route('customers.show', $customerId) : route('customers.index') }}" class="text-sm text-stone-600">Cancelar</a></div>
    </form>
</div>
