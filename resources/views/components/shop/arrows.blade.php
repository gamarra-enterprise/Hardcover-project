@props(['target'])

<span style="display: flex; gap: .5rem">
    <button type="button" class="arr" data-scroll="-1" data-target="{{ $target }}" aria-label="Anterior">←</button>
    <button type="button" class="arr" data-scroll="1" data-target="{{ $target }}" aria-label="Siguiente">→</button>
</span>
