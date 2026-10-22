@extends('layouts.app')

@section('title', 'Forgot password')

@section('content')
    <section class="card auth-card">
        <h1>Forgot your password?</h1>
        <p>Enter your email address to request a password reset link.</p>

        <form method="POST" action="{{ url('/forgot-password') }}">
            @csrf

            <label for="email">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
                autofocus
            >

            <button class="submit" type="submit">Send reset link</button>
        </form>

        <p class="links">
            <a href="{{ route('login') }}">Back to login</a>
        </p>
    </section>
@endsection