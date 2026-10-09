@props(['title', 'subtitle' => null, 'icon' => null, 'id' => null])

<section class="wrap rv" @if ($id) id="{{ $id }}" @endif style="padding-block: 64px 0">
    <div class="sec-head">
        <div style="display: flex; align-items: center; gap: .8rem; min-width: 0">
            @if ($icon)<span style="color: var(--accent-deep); display: flex"><x-shop.icon :name="$icon" size="22" /></span>@endif
            <div>
                <h2 style="font-size: clamp(1.8rem, 3.4vw, 2.5rem); font-weight: 700">{{ $title }}</h2>
                @if ($subtitle)<p class="muted" style="margin-top: .2rem; font-size: 14.5px">{{ $subtitle }}</p>@endif
            </div>
        </div>
        @isset($link){{ $link }}@endisset
    </div>
    {{ $slot }}
</section>
