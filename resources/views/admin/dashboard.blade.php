@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    <h1>DietsDNA administration</h1>

    <p>
        Welcome, {{ auth()->user()->name }}.
        Manage your nutrition platform here.
    </p>

    <p>
        <a href="{{ route('dashboard') }}">Open customer dashboard</a>
    </p>

    <div class="grid">
        <section class="card">
            <h2>Ingredients</h2>
            <p>Manage foods, preparation states and nutrition per 100g.</p>
            <span class="badge">Coming next</span>
        </section>

        <section class="card">
            <h2>Recipes</h2>
            <p>Manage ingredients, quantities and recipe portions.</p>
            <span class="badge">Coming soon</span>
        </section>

        <section class="card">
            <h2>Customers</h2>
            <p>Manage registered customer accounts.</p>
            <span class="badge">Coming soon</span>
        </section>
    </div>
@endsection