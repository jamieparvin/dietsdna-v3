@extends('layouts.app')

@section('title', 'Create an account')

@section('content')
    <section class="card auth-card">
        <h1>Create your account</h1>
        <p>Start building your personal nutrition plan.</p>

        <form method="POST" action="{{ url('/register') }}">
            @csrf

            <label for="name">Full name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                autocomplete="name"
                required
                autofocus
            >

            <label for="email">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
            >

            <label for="password_confirmation">Confirm password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            >

            <button class="submit" type="submit">Create account</button>
        </form>

        <p class="links">
            Already registered?
            <a href="{{ route('login') }}">Log in</a>
        </p>
    </section>
@endsection