<li id="shopping-item-{{ $item['item_key'] }}" class="sl-row {{ $item['is_checked'] ? 'is-checked' : '' }}">
    <form method="POST" action="{{ route('shopping-list.update') }}" data-shopping-check data-name="{{ $item['name'] }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="week_start" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="item_key" value="{{ $item['item_key'] }}">
        <input type="hidden" name="quantity_g" value="{{ $item['quantity_g'] }}">
        <input type="hidden" name="checked" value="{{ $item['is_checked'] ? '0' : '1' }}">
        <button type="submit" class="sl-check" aria-pressed="{{ $item['is_checked'] ? 'true' : 'false' }}"
            aria-label="{{ $item['is_checked'] ? 'Mark still needed: ' : 'Mark purchased: ' }}{{ $item['name'] }}">
            <span aria-hidden="true">{{ $item['is_checked'] ? "\u{2713}" : '+' }}</span>
        </button>
    </form>
    <div class="sl-info"><strong class="sl-name">{{ $item['name'] }}</strong><span class="sl-preparation">{{ $item['preparation_state'] }}</span></div>
    <span class="sl-quantity">{{ rtrim(rtrim($item['quantity_g'], '0'), '.') }} g</span>
</li>
