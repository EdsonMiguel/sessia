@props(['name', 'label', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" {{ $attributes->class(['field-input']) }} aria-describedby="{{ $name }}-error" @error($name) aria-invalid="true" @enderror>
    @error($name) <p id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</p> @enderror
</div>
