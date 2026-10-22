@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p>
        Your DietsDNA account is ready.
        Your nutrition tools will appear here as we build them.
    </p>

    <div class="grid">
        <section class="card">
            <h2>Your nutrition profile</h2>
            <p>Your goals, dietary preferences and allergies.</p>
            <span class="badge">Coming next</span>
        </section>

        <section class="card">
            <h2>Your meal plan</h2>
            <p>Your meals, portions and shopping list.</p>
            <span class="badge">Coming soon</span>
        </section>

        <section class="card">
            <h2>Your progress</h2>
            <p>Your food logs and progress over time.</p>
            <span class="badge">Coming soon</span>
        </section>
    </div>
@endsection