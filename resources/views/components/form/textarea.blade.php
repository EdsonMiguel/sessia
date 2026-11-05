@props(['name', 'label'])
<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="4" {{ $attributes->class(['field-input']) }} aria-describedby="{{ $name }}-error" @error($name) aria-invalid="true" @enderror></textarea>
    @error($name) <p id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</p> @enderror
</div>
