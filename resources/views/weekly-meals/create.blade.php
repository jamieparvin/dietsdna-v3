@extends('layouts.app')
@section('title', 'Generate my week')
@section('content')
@include('weekly-meals.styles')
<style>
    .wm-page .wm-button { min-height:44px; }
    .ww-modes { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:18px 0; }
    .wm-page .ww-mode { display:flex; gap:12px; padding:16px; border:1px solid #dce6de; border-radius:12px; cursor:pointer; }
    .ww-mode input { width:20px!important; height:20px; flex:none; margin-top:3px; accent-color:#214e3d; }
    .ww-mode:has(input:checked) { background:#edf3eb; border-color:#214e3d; }
    .ww-mode strong,.ww-mode span { display:block; }
    .ww-mode span { margin-top:6px; line-height:1.6; }
    @media(max-width:640px) { .ww-modes { grid-template-columns:1fr; } }
</style>
<div class="wm-page">
    <h1>Generate my week</h1>
    <p class="wm-muted">Build a new preview from your food choices and current calorie and macro guides.</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $start->toDateString()]) }}">Back to meal plan</a></div>
    @if($errors->any())
        <div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @if($blocked)
        <div class="wm-panel"><p>{{ $blocked }}</p><a href="{{ route('nutrition-profile.edit') }}">View nutrition profile</a></div>
    @else
        <section class="wm-panel">
            <h2>Build a weekly preview</h2>
            <p class="wm-muted">
                {{ $source['context']['meal_count'] }} full meals
                @if(($source['context']['snack_count'] ?? 0) > 0)
                    and {{ $source['context']['snack_count'] }} optional {{ $source['context']['snack_count'] === 1 ? 'snack' : 'snacks' }}
                @else
                    and no optional snacks
                @endif
                per day: {{ 7 * count($source['slots']) }} slots across the week.
            </p>
            <form method="POST" action="{{ route('weekly-meals.store') }}" id="weekly-start-form">
                @csrf
                <fieldset style="border:0;padding:0;margin:0">
                    <legend>How would you like to build this week?</legend>
                    <div class="ww-modes">
                        <label class="ww-mode">
                            <input type="radio" name="mode" value="fill" @checked(old('mode', $mode) === 'fill') required>
                            <div><strong>Fill empty slots</strong><span class="wm-muted">Keep meals already in the plan and suggest meals for the gaps.</span></div>
                        </label>
                        <label class="ww-mode">
                            <input type="radio" name="mode" value="replace" @checked(old('mode', $mode) === 'replace') required>
                            <div><strong>Replace entire week</strong><span class="wm-muted">Preview a complete new week. Your current plan stays in place until you choose Replace this week.</span></div>
                        </label>
                    </div>
                </fieldset>
                <label for="weekly-start">Choose a date in the week</label>
                <div class="wm-toolbar">
                    <input id="weekly-start" type="date" name="start_date" value="{{ old('start_date', $start->toDateString()) }}" required>
                    <button class="wm-button" id="weekly-start-button">Generate weekly preview</button>
                </div>
            </form>
            <p class="wm-muted">The preview runs Monday to Sunday. Meal ideas can repeat across the week. Portions are adjusted against your daily and per-meal guides. Review the actual totals before confirming.</p>
            <p class="wm-muted">Generation uses your configured AI account and can take several minutes. It runs in the background. Nothing is added or removed until you confirm. Saved meals remain available.</p>
        </section>
    @endif
    @if($recent->isNotEmpty())
        <section class="wm-panel"><h2>Recent weekly previews</h2>
            @foreach($recent as $item)
                <p><a href="{{ route('weekly-meals.show', $item->id) }}">Week of {{ $item->start_date->format('j M Y') }}</a>
                    <span class="wm-muted">· {{ ucfirst($item->status) }} · {{ $item->mode === 'replace' ? 'Replacement' : 'Fill empty slots' }}</span>
                </p>
            @endforeach
        </section>
    @endif
</div>
<script>document.getElementById('weekly-start-form')?.addEventListener('submit',()=>{const b=document.getElementById('weekly-start-button');b.disabled=true;b.textContent='Starting…';});</script>
@endsection
