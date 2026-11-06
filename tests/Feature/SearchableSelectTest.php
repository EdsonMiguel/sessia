<?php

use Illuminate\Support\Facades\Blade;

it('preserves livewire bindings validation and disabled options in the searchable control', function () {
    $html = Blade::render('<x-form.select id="procedure" wire:model.live="items.0.procedure_id" required :disabled="$disabled"><option value="">Selecione</option><option value="12" disabled>Inativo</option><option value="13">{{ $label }}</option></x-form.select>', [
        'disabled' => true, 'label' => '<script>conteúdo</script>',
    ]);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $select = $document->getElementById('procedure');
    expect($select->getAttribute('wire:model.live'))->toBe('items.0.procedure_id')
        ->and($select->hasAttribute('required'))->toBeTrue()
        ->and($select->hasAttribute('disabled'))->toBeTrue()
        ->and($select->getElementsByTagName('option')->item(1)->hasAttribute('disabled'))->toBeTrue()
        ->and($document->getElementsByTagName('script')->length)->toBe(0);
    expect($html)->toContain('wire:ignore.self', 'data-select-ui wire:ignore');
});

it('retains Alpine bindings for installment selection and normal native fallback', function () {
    $html = Blade::render('<x-form.select id="installment" x-model="selected" x-on:change="generate()" class="field-input"><option value="0">Primeira</option></x-form.select>');
    expect($html)->toContain('x-model="selected"', 'x-on:change="generate()"', 'value="0"')
        ->not->toContain('aria-hidden="true"', 'tabindex="-1"');
});
