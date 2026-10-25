@extends('layouts.app')
@section('title', 'Shopping list')
@section('content')
<style>
.sl-page {width:100%;min-width:0;color:#183d32;}
.sl-header,.sl-week,.sl-summary,.sl-row {display:flex;align-items:center;gap:12px;}
.sl-header {justify-content:space-between;flex-wrap:wrap;margin-bottom:18px;}
.sl-header h1 {margin:0 0 6px;}.sl-muted {margin:0;color:#61756a;font-size:13px;line-height:1.6;}
.sl-button,.sl-jumps a {display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:8px 12px;border:1px solid #d5e0d8;border-radius:8px;background:#fff;color:#234d37;text-decoration:none;font:inherit;}
.sl-week {flex-wrap:wrap;padding:12px 14px;background:#fff;border:1px solid #dfe7e1;border-radius:12px;}
.sl-week form {display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0;}.sl-week label {margin:0;font-size:13px;}
.sl-week select {min-height:44px;max-width:100%;padding:8px;border:1px solid #b9cbc0;border-radius:8px;background:#fff;color:#183d32;font:inherit;font-size:16px;}
.sl-summary {justify-content:space-between;flex-wrap:wrap;margin:14px 0;font-size:14px;}
.sl-jumps {display:flex;flex-wrap:wrap;gap:6px;margin:12px 0;}.sl-jumps a {font-size:13px;padding:6px 10px;}
.sl-checklist {border:1px solid #dfe7e1;border-radius:12px;background:#fff;overflow:hidden;}
.sl-category h2,.sl-purchased summary {margin:0;padding:12px 16px;background:#edf4ee;color:#214e3d;font-size:16px;}
.sl-items {list-style:none;margin:0;padding:0 14px;}.sl-row {padding:7px 0;border-bottom:1px solid #edf1eb;scroll-margin-top:16px;}.sl-row:last-child {border-bottom:0;}
.sl-row form {flex:none;margin:0;}.sl-check {display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:1px solid #b9cbc0;border-radius:8px;background:#fff;color:#214e3d;font-size:20px;cursor:pointer;}
.sl-info {flex:1;min-width:0;}.sl-name {display:block;font-size:14px;line-height:1.5;overflow-wrap:anywhere;}.sl-preparation {display:block;color:#61756a;font-size:12px;overflow-wrap:anywhere;}
.sl-quantity {flex:none;white-space:nowrap;font-size:14px;font-weight:700;}.sl-row.is-checked .sl-name {text-decoration:line-through;color:#61756a;}.sl-row.is-checked .sl-check {background:#214e3d;color:#fff;}
.sl-purchased {margin-top:16px;border:1px solid #dfe7e1;border-radius:12px;background:#fff;overflow:hidden;}.sl-purchased summary {min-height:44px;cursor:pointer;}
.sl-notice {padding:12px 16px;margin:14px 0;border:1px solid #d9e5db;border-radius:10px;background:#edf4ee;font-size:14px;line-height:1.6;}.sl-warning {background:#fff7e9;border-color:#d9bf89;}
.sl-review {margin-top:18px;border:1px solid #d9bf89;border-radius:12px;padding:16px;background:#fff;}.sl-review h2 {margin-top:0;}.sl-review ul {list-style:none;margin:0;padding:0;}.sl-review li {padding:12px 0;border-top:1px solid #edf1eb;overflow-wrap:anywhere;}.sl-review p {margin:4px 0;font-size:13px;}.sl-review a {display:inline-flex;align-items:center;min-height:44px;}
.sl-page a:focus-visible,.sl-page button:focus-visible,.sl-page select:focus-visible,.sl-page summary:focus-visible {outline:3px solid #bc9148;outline-offset:3px;}.sl-check:disabled {opacity:.6;cursor:wait;}
.sl-empty {padding:20px 16px;border:1px solid #dfe7e1;border-radius:12px;background:#fff;}
@media(max-width:550px) {.sl-week {gap:6px;padding:10px;}.sl-week form {width:100%;}.sl-week select {flex:1;min-width:0;}.sl-week label {width:100%;}.sl-quantity {font-size:13px;}.sl-row {gap:9px;}.sl-items {padding:0 10px;}}
</style>
<div class="sl-page">
    <header class="sl-header">
        <div><h1>Your shopping list</h1><p class="sl-muted">Confirmed planned meals for {{ $weekStart->format('j M') }} &ndash; {{ $weekEnd->format('j M Y') }}.</p></div>
        <a class="sl-button" href="{{ route('meal-plan.index', ['date' => $weekStart->toDateString()]) }}">View meal plan</a>
    </header>
    <nav class="sl-week" aria-label="Shopping week">
        <a class="sl-button" aria-label="Previous shopping week" href="{{ route('shopping-list.index', ['date' => $weekStart->subWeek()->toDateString()]) }}">&larr;</a>
        <form method="GET" action="{{ route('shopping-list.index') }}">
            <label for="shopping-week">Week beginning</label>
            <select id="shopping-week" name="date">
                @foreach($weekOptions as $week)
                    <option value="{{ $week->toDateString() }}" @selected($week->isSameDay($weekStart))>{{ $week->format('j M Y') }}</option>
                @endforeach
            </select>
            <button class="sl-button" type="submit">View</button>
        </form>
        <a class="sl-button" aria-label="Next shopping week" href="{{ route('shopping-list.index', ['date' => $weekStart->addWeek()->toDateString()]) }}">&rarr;</a>
        <a class="sl-button" href="{{ route('shopping-list.index') }}">This week</a>
    </nav>
    @if($errors->any())
        <div class="sl-notice sl-warning" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @if($incomplete)
        <div class="sl-notice sl-warning" role="status"><strong>Incomplete shopping list.</strong> Purchases below cover calculable ingredients only. {{ count($needsReview) }} planned ingredient {{ count($needsReview) === 1 ? 'entry needs' : 'entries need' }} review. <a href="#shopping-needs-review">Review unresolved ingredients</a>.</div>
    @endif
    <div class="sl-summary"><span>{{ $mealCount }} planned {{ $mealCount === 1 ? 'meal' : 'meals' }} &middot; {{ $itemCount }} purchasable {{ $itemCount === 1 ? 'item' : 'items' }}</span><strong id="shopping-count" role="status">{{ $checkedCount }} of {{ $itemCount }} items purchased</strong></div>
    <p id="shopping-feedback" role="status" aria-live="polite" class="sl-muted"></p>
    @if($groups->isNotEmpty())
        <nav class="sl-jumps" aria-label="Jump to shopping category">
            @foreach($groups as $category => $items)<a href="#shopping-category-{{ $loop->index }}">{{ $category }}</a>@endforeach
            <a href="#shopping-purchased">Purchased</a>
            @if($incomplete)<a href="#shopping-needs-review">Needs review</a>@endif
        </nav>
        <div class="sl-checklist">
            @foreach($groups as $category => $items)
                <section class="sl-category" aria-labelledby="shopping-category-{{ $loop->index }}">
                    <h2 id="shopping-category-{{ $loop->index }}" tabindex="-1">{{ $category }}</h2>
                    <ul class="sl-items">@foreach($items as $item)@include('shopping-list.item')@endforeach</ul>
                </section>
            @endforeach
        </div>
    @elseif($itemCount > 0)
        <p class="sl-notice">All calculable purchases are checked. Open Purchased items to review them.</p>
    @elseif($mealCount === 0)
        <section class="sl-empty"><h2>No meals planned for this week</h2><p>Add confirmed meals to your plan to build a shopping list.</p></section>
    @else
        <p class="sl-notice">No calculable purchases yet. Review the planned ingredients below.</p>
    @endif
    <details class="sl-purchased" id="shopping-purchased" @if($purchasedOpen) open @endif>
        <summary>Purchased items</summary>
        <ul class="sl-items">@foreach($purchasedItems as $item)@include('shopping-list.item')@endforeach</ul>
        @if($purchasedItems->isEmpty())<p class="sl-muted" style="padding:12px 16px">No purchased items when this page opened.</p>@endif
    </details>
    @if($incomplete)
        <section class="sl-review" id="shopping-needs-review" aria-labelledby="shopping-review-title">
            <h2 id="shopping-review-title">Needs review</h2>
            <p class="sl-muted">These are original planned amounts, not purchase weights. They are excluded from purchase totals and checks.</p>
            <ul>@foreach($needsReview as $row)
                <li><strong>{{ $row['name'] }}</strong> <span class="sl-preparation">{{ $row['preparation_state'] }}</span>
                    <p>Planned quantity: {{ $row['quantity_label'] }} g</p><p>{{ $row['reason'] }}</p>
                    <a href="{{ route('meal-plan.index', ['date' => $row['plan_date']]) }}#planned-meal-{{ $row['entry_id'] }}">{{ $row['meal_name'] }} &middot; {{ $row['plan_date'] }}</a>
                </li>
            @endforeach</ul>
        </section>
    @endif
    <p class="sl-muted" style="margin-top:16px">Raw, dry and recognised ready-to-eat weights are used directly; other weights require explicit purchase mappings. Checked items stay in place while you shop; refresh to regroup them under Purchased items. Refresh after changing your plan.</p>
</div>
<script>
(() => {
    const feedback = document.getElementById('shopping-feedback');
    document.querySelectorAll('[data-shopping-check]').forEach(form => {
        form.addEventListener('submit', async event => {
            if (!window.fetch) return;
            event.preventDefault();
            const button = form.querySelector('button');
            if (button.disabled) return;
            button.disabled = true;
            const hadFocus = document.activeElement === button;
            try {
                const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}});
                if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                    throw new Error('Your session may have expired. Refresh the list before trying again.');
                }
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || 'Unable to update. Refresh the list and try again.');
                form.closest('.sl-row').classList.toggle('is-checked', result.is_checked);
                button.setAttribute('aria-pressed', String(result.is_checked));
                button.setAttribute('aria-label', (result.is_checked ? 'Mark still needed: ' : 'Mark purchased: ') + form.dataset.name);
                button.querySelector('span').textContent = result.is_checked ? '\u2713' : '+';
                form.querySelector('[name="checked"]').value = result.is_checked ? '0' : '1';
                document.getElementById('shopping-count').textContent = `${result.checked_count} of ${result.item_count} items purchased`;
                feedback.textContent = `${form.dataset.name}: ${result.is_checked ? 'marked purchased' : 'still needed'}.`;
            } catch (error) {
                feedback.textContent = error.message || 'Unable to update. Refresh the list and try again.';
            } finally {
                button.disabled = false;
                if (hadFocus) button.focus({preventScroll:true});
            }
        });
    });
})();
</script>
@endsection
