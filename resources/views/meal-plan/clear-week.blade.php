@extends('layouts.app')
@section('title', 'Clear this week')
@section('content')
@include('weekly-meals.styles')
<style>.wm-page .wm-button{min-height:44px}</style>
<div class="wm-page">
    <h1>Clear this week?</h1>
    <p class="wm-muted">{{ $start->format('j M') }} – {{ $start->addDays(6)->format('j M Y') }}</p>
    @if($errors->any())
        <div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    <section class="wm-panel">
        <h2>{{ $count }} planned {{ $count === 1 ? 'meal' : 'meals' }}</h2>
        <p>This removes all dated meal entries for this Monday-to-Sunday week, including meals from a previous meal pattern.</p>
        <p>Your Saved meals, food preferences and nutrition profile stay available. Any queued or unconfirmed weekly previews for these dates will be cancelled.</p>
        <p class="wm-muted">The previous day-by-day arrangement will be removed. You can build another plan using your Saved meals or generate a new weekly preview.</p>
        <form method="POST" action="{{ route('meal-plan.clear.store') }}" id="clear-week-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label style="display:flex;align-items:flex-start;gap:12px;line-height:1.6;margin:20px 0;cursor:pointer">
                <input type="checkbox" name="clear_confirmed" value="1" required style="width:20px!important;height:20px;flex:none;margin-top:3px">
                <span>Remove the planned meals for {{ $start->format('j M') }} – {{ $start->addDays(6)->format('j M Y') }}.</span>
            </label>
            <div class="wm-toolbar">
                <button type="submit" class="wm-button" id="clear-week-button">Clear this week</button>
                <a class="wm-button wm-secondary" href="{{ route('meal-plan.index', ['date' => $start->toDateString()]) }}">Keep my plan</a>
            </div>
        </form>
    </section>
</div>
<script>document.getElementById('clear-week-form')?.addEventListener('submit',()=>{const b=document.getElementById('clear-week-button');b.disabled=true;b.textContent='Clearing week…';});</script>
@endsection
