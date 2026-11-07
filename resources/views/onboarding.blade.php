@extends('layouts.app')
@section('title', 'Getting started')
@section('content')
    <style>
        .setup-button { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:13px 20px; background:#167451; color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
        .setup-button:hover { background:#125d41; }
        @media(max-width:640px) { .setup-button { width:100%; } }
    </style>
    <h1>{{ $setup['complete'] ? 'Your setup is complete' : 'Get started with DietsDNA' }}</h1>
    <p>Build your nutrition profile, review your food preferences, then explore your first weekly meal preview.</p>
    @include('onboarding-progress')
    <section class="card" aria-labelledby="setup-next">
        @if(!$setup['profileComplete'])
            <h2 id="setup-next">Start with your nutrition profile</h2>
            <p>Add your measurements, activity level and goals to help us personalise your meal plan.</p>
        @elseif(!$setup['preferencesComplete'])
            <h2 id="setup-next">Review your food preferences</h2>
            <p>Choose foods you enjoy and those you’d prefer to avoid. We’ll use these choices when suggesting meals. You can update them at any time.</p>
        @elseif($generationBlocked !== null)
            <h2 id="setup-next">Your setup is complete</h2>
            <p>Your profile and food preferences are saved. Your existing meals and plans stay saved.</p>
        @else
            <h2 id="setup-next">Next: your first weekly meal preview</h2>
            <p>Your profile and preference setup is finished. Generating and confirming a plan are optional. Your existing meals and plans stay saved.</p>
        @endif
        @if($generationBlocked !== null && ($setup['targets']['status'] !== 'review' || $generationBlocked !== $setup['targets']['message']))
            <p role="status"><strong>Automatic meal generation unavailable.</strong> {{ $generationBlocked }}</p>
        @endif
        @if($preview ?? null)
            <p>{{ $preview->status === 'ready' ? 'Your weekly preview is ready to review.' : 'Your weekly preview is being prepared. You can leave and return while it runs.' }}</p>
            <a class="setup-button" href="{{ route('weekly-meals.show', $preview->id) }}">{{ $preview->status === 'ready' ? 'Review weekly preview' : 'View preview progress' }}</a>
        @elseif($setup['complete'] && $generationBlocked !== null)
            <a class="setup-button" href="{{ route('nutrition-profile.edit') }}">View nutrition profile</a>
        @else
            <a class="setup-button" href="{{ route($setup['route']) }}">{{ match($setup['step']) { 1 => 'Continue to nutrition profile', 2 => 'Continue to food preferences', default => 'Continue to weekly preview' } }}</a>
        @endif
        @include('partials.food-allergy-notice')
        @include('partials.nutrition-estimate-disclaimer')
        <p>You can edit your nutrition profile and food preferences at any time using the menu.</p>
    </section>
@endsection
