@props(['title', 'open' => false])

<div class="acc" :class="{ open }" x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <button type="button" class="acc-h" x-on:click="open = !open" :aria-expanded="open.toString()">{{ $title }}<span aria-hidden="true">+</span></button>
    <div class="acc-b"><div><div style="padding-bottom: 16px; line-height: 1.7">{{ $slot }}</div></div></div>
</div>
