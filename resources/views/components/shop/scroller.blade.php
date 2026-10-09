@props(['class' => 'chips-row', 'label' => 'Opciones'])

{{-- A row that scrolls sideways, with arrows that appear only when something is hidden on that side. --}}
<div class="scroller" x-data="{
        l: false, r: false,
        check() { const e = this.$refs.track; if (! e) return; this.l = e.scrollLeft > 4; this.r = e.scrollLeft + e.clientWidth < e.scrollWidth - 4; },
        go(dir) { const e = this.$refs.track; e.scrollBy({ left: dir * Math.max(180, e.clientWidth * 0.7), behavior: 'smooth' }); },
    }"
    x-init="check(); new ResizeObserver(() => check()).observe($refs.track); new MutationObserver(() => $nextTick(() => check())).observe($refs.track, { childList: true, subtree: true, characterData: true })">
    <button type="button" class="scroll-btn left" x-show="l" x-cloak x-on:click="go(-1)" aria-label="Ver opciones anteriores: {{ $label }}">←</button>
    <div {{ $attributes->merge(['class' => $class]) }} x-ref="track" x-on:scroll.passive="check()">{{ $slot }}</div>
    <button type="button" class="scroll-btn right" x-show="r" x-cloak x-on:click="go(1)" aria-label="Ver más opciones: {{ $label }}">→</button>
</div>
