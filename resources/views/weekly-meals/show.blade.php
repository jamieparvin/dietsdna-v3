@extends('layouts.app')
@section('title', 'Weekly meal preview')
@section('content')
@include('weekly-meals.styles')
<div class="wm-page">
    <h1>Your weekly preview</h1>
    <p class="wm-muted">{{ $draft->start_date->format('j M') }} – {{ $draft->start_date->addDays(6)->format('j M Y') }}</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $draft->start_date->toDateString()]) }}">View meal plan</a><a href="{{ route('weekly-meals.create', ['date' => $draft->start_date->toDateString()]) }}">Weekly generation</a></div>
    @if($errors->any())<div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    @if(in_array($draft->status, ['queued', 'generating'], true))
        <section class="wm-panel">
            <h2>{{ $draft->status === 'queued' ? 'Waiting to start' : 'Creating meal options' }}</h2>
            <p id="weekly-progress" class="wm-muted" role="status">{{ $draft->completed_types }} of {{ $typeCount }} meal types completed.</p>
            <p class="wm-muted">We’re preparing your meals. You can leave this page and return later to check your progress.</p>
            <form method="POST" action="{{ route('weekly-meals.cancel', $draft->id) }}">@csrf<button class="wm-button wm-secondary">Cancel preview</button></form>
            <p class="wm-muted">Cancelling stops this preview. Your existing meal plan stays unchanged.</p>
        </section>
    @elseif($draft->status === 'failed')
        <div class="wm-notice wm-error" role="alert">{{ $draft->error }}</div>
        <p class="wm-muted">The request was not automatically retried. Existing planned meals were not changed.</p>
    @elseif($draft->status === 'cancelled')
        <div class="wm-notice">This preview was cancelled. Your meal plan was not changed.</div>
    @elseif($draft->status === 'confirmed')
        <div class="wm-notice">This preview has been added. Open your meal plan to see your latest meals and make swaps.</div>
    @elseif($draft->status === 'ready')
        <div class="wm-notice">Review your meals below. “Keep existing” meals are already in your plan. Confirming fills empty slots only and also keeps the new meal options in Saved meals. These meals are not guaranteed to meet your daily calorie or macro guides.</div>
        <div class="wm-grid">
            @foreach($days as $day)
                <section class="wm-day">
                    <div class="wm-day-header"><h2>{{ $day['date']->format('l, j M') }}</h2><p>{{ number_format($day['totals']['energy_kcal']) }} kcal · {{ number_format($day['totals']['protein_g'], 1) }} g protein · {{ number_format($day['totals']['carbohydrate_g'], 1) }} g total carbs · {{ number_format($day['totals']['fat_g'], 1) }} g fat</p></div>
                    @foreach($day['rows'] as $row)
                        <div class="wm-meal">
                            <div class="wm-slot"><span>{{ $row['label'] }}</span><span class="wm-badge">{{ $row['existing'] ? 'Keep existing' : 'New suggestion' }}</span></div>
                            @if($row['snapshot'])
                                @php($meal = $row['snapshot'])
                                <div class="wm-name">{{ $meal['name'] }}</div>
                                <div class="wm-macros" style="display:flex; flex-wrap:wrap; gap:6px 14px;">
                                    <span>{{ number_format($meal['totals']['energy_kcal']) }} kcal</span>
                                    <span>{{ number_format($meal['totals']['protein_g'], 1) }} g protein</span>
                                    <span>{{ number_format($meal['totals']['carbohydrate_g'], 1) }} g total carbs</span>
                                    <span>{{ number_format($meal['totals']['fat_g'], 1) }} g fat</span>
                                </div>
                                <details><summary>Ingredients and preparation</summary><ul class="wm-ingredients">@foreach($meal['ingredients'] as $ingredient)<li>{{ $ingredient['name'] }} — {{ number_format($ingredient['quantity_g'], 1) }} g ({{ $ingredient['preparation_state'] }})</li>@endforeach</ul><div class="wm-method">{{ $meal['instructions'] }}</div></details>
                            @else
                                <p class="wm-muted">No suggestion for this slot. It may have been removed from your plan while the preview was open.</p>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
        <div class="wm-confirm"><span class="wm-muted">Keep your existing meals and add the remaining suggestions.</span><div class="wm-toolbar" style="margin:0"><form method="POST" action="{{ route('weekly-meals.cancel', $draft->id) }}">@csrf<button class="wm-button wm-secondary">Discard preview</button></form><form method="POST" action="{{ route('weekly-meals.confirm', $draft->id) }}" id="weekly-confirm-form">@csrf<button class="wm-button" id="weekly-confirm-button">Add this week to my plan</button></form></div></div>
    @endif
</div>
@if(in_array($draft->status, ['queued', 'generating'], true))
<script>
(()=>{
    const url=@json(route('weekly-meals.status', $draft->id));
    const progress=document.getElementById('weekly-progress');
    async function poll(){
        try{
            const response=await fetch(url,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!response.ok)throw new Error('Status unavailable');
            const data=await response.json();
            if(!['queued','generating'].includes(data.status)){window.location.reload();return;}
            progress.textContent=`${data.completed_types} of {{ $typeCount }} meal types completed.`;
            setTimeout(poll,4000);
        }catch(error){progress.textContent='Could not update progress. Refresh this page to check again.';}
    }
    setTimeout(poll,4000);
})();
</script>
@endif
<script>document.getElementById('weekly-confirm-form')?.addEventListener('submit',()=>{const b=document.getElementById('weekly-confirm-button');b.disabled=true;b.textContent='Adding meals…';});</script>
@endsection
