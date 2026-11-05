@props(['data'])

<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-6" wire:key="pix-{{ hash('sha256', json_encode($data)) }}">
    <h2 class="text-lg font-semibold">Pagamento por Pix</h2>
    @if(empty($data['installments']))
        <p class="mt-3 text-sm text-teal-800">Esta cobrança não possui saldo pendente.</p>
    @elseif(!$data['key'] || !$data['receiver'] || !$data['city'])
        <p class="mt-3 text-sm text-stone-500">Cadastre a chave Pix, o nome e a cidade do recebedor em <a href="{{ route('business.settings') }}" class="font-semibold text-teal-800 underline"><x-icon name="settings" /> <span>Configurações do negócio</span></a>.</p>
    @else
        <div x-data="pixPayment(@js($data))" class="mt-4 space-y-4">
            <p class="text-sm text-stone-600">Recebedor: {{ $data['receiver'] }} · {{ $data['city'] }}</p>
            <div>
                <label for="pix-installment" class="field-label">Parcela para pagamento</label>
                <select id="pix-installment" x-model="selected" x-on:change="generate()" class="field-input">
                    @foreach($data['installments'] as $installment)
                        <option value="{{ $installment['id'] }}">Parcela {{ $installment['number'] }} · {{ $installment['due'] }} · {{ $installment['formattedAmount'] }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-stone-500">O valor considera os pagamentos já registrados nesta parcela.</p>
            </div>
            <p class="text-sm text-stone-600">{{ $data['description'] }}</p>
            <p x-cloak x-show="error" x-text="error" class="field-error" role="alert"></p>
            <div x-cloak x-show="code" class="space-y-3">
                <label for="pix-code" class="field-label">Pix Copia e Cola</label>
                <textarea id="pix-code" x-ref="code" x-bind:value="code" readonly rows="4" class="field-input font-mono text-sm" x-on:focus="$el.select()"></textarea>
                <p class="text-xs text-stone-500">Descrição no Pix: <span x-text="description || 'Consulte a descrição da cobrança acima.'"></span></p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" x-on:click="copy()" class="primary-button"><x-icon name="copy" /> <span>Copiar Pix</span></button>
                    <a x-bind:href="whatsappUrl" target="_blank" rel="noopener noreferrer" class="primary-button"><x-icon name="chat" /> <span>Enviar pelo WhatsApp ↗</span></a>
                </div>
                <p x-show="!phone" class="text-xs text-stone-500">Telefone sem DDD ou DDI válido. Escolha a conversa no WhatsApp.</p>
                <p role="status" x-text="feedback" class="text-sm text-teal-800"></p>
            </div>
            <p class="text-xs text-stone-500">Confira o recebedor e o valor antes de pagar. O WhatsApp abre a mensagem para revisão e envio. A confirmação do recebimento no Sessia continua manual.</p>
        </div>
    @endif
</section>
