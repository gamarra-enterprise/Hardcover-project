@props(['summary'])

@use('App\Enums\CartLineIssue')
@use('App\Support\Money')

<ul class="cart-lines">
    @foreach ($summary->lines as $line)
        <li class="cart-line {{ $line->issue ? 'has-issue' : '' }}" wire:key="line-{{ $line->item->id }}">
            <a class="cart-thumb" href="{{ route('products.show', $line->product) }}" tabindex="-1" aria-hidden="true">
                <x-shop.cover :product="$line->product" />
            </a>

            <div class="cart-info">
                <a class="cart-name" href="{{ route('products.show', $line->product) }}">{{ $line->product->name }}</a>
                @if ($line->product->bookDetail)
                    <div class="muted" style="font-size: 13.5px">{{ $line->product->bookDetail->author }}</div>
                @endif
                <div class="num" style="margin-top: .2rem">{{ Money::format($line->unitPrice) }}</div>

                @if ($line->issue)
                    <p class="issue" role="alert">{{ $line->issueMessage() }}</p>
                    @if ($line->issue === CartLineIssue::EXCEEDS_STOCK)
                        <button type="button" class="tlink" wire:click="setTo({{ $line->item->id }}, {{ $line->available }})">Ajustar a {{ $line->available }}</button>
                    @endif
                @endif

                <div class="cart-actions">
                    <div class="qty" role="group" aria-label="Cantidad de {{ $line->product->name }}">
                        <button type="button" wire:click="changeBy({{ $line->item->id }}, -1)" aria-label="Menos">−</button>
                        <span class="num">{{ $line->quantity }}</span>
                        <button type="button" wire:click="changeBy({{ $line->item->id }}, 1)" aria-label="Más" @disabled($line->available <= $line->quantity)>+</button>
                    </div>
                    <button type="button" class="tlink" wire:click="remove({{ $line->item->id }})">Quitar</button>
                </div>
            </div>

            <b class="num cart-total">{{ Money::format($line->total) }}</b>
        </li>
    @endforeach
</ul>
