<section class="mt-8 max-w-2xl rounded-2xl border border-red-200 bg-white p-4 sm:p-8" aria-labelledby="clear-data-title">
        <h2 id="clear-data-title" class="flex items-center gap-2 text-lg font-semibold text-red-800"><x-icon name="trash" /> Limpar dados cadastrados</h2>
        <p class="mt-3 text-sm text-stone-600">Apaga permanentemente os dados do negócio <strong>{{ $businessName }}</strong>: clientes, endereços, preços personalizados, procedimentos, pacotes, contratações, agenda, bloqueios, sessões, cobranças e pagamentos, incluindo seus históricos.</p>
        <p class="mt-3 text-sm text-stone-600">Sua conta, senha, vínculos e configurações do negócio serão mantidos. Outros negócios não serão afetados. Será mantido apenas um registro da limpeza, com responsável, data e quantidades apagadas.</p>
        <details class="mt-5" @if($errors->has('clearPassword') || $errors->has('clearConfirmation')) open @endif>
            <summary class="min-h-12 cursor-pointer rounded-xl border border-red-300 px-4 py-3 text-sm font-semibold text-red-800">Quero limpar os dados deste negócio</summary>
            <form wire:submit="clearData" class="mt-5 space-y-5">
                <p class="text-sm font-semibold text-red-800">Esta ação não pode ser desfeita. Todos os dados listados acima serão excluídos, inclusive registros financeiros.</p>
                <x-form.input name="clearPassword" label="Sua senha atual" type="password" wire:model="clearPassword" autocomplete="current-password" required />
                <x-form.input name="clearConfirmation" label="Digite APAGAR TUDO para confirmar" wire:model="clearConfirmation" autocomplete="off" spellcheck="false" required />
                <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50 sm:w-auto"><x-icon name="trash" /> Apagar permanentemente os dados</button>
            </form>
        </details>
    </section>