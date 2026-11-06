<searchable-select wire:ignore.self>
    <select {{ $attributes }}>{{ $slot }}</select>
    <div data-select-ui wire:ignore></div>
</searchable-select>
