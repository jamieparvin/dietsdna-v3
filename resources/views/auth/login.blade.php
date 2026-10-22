@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <section class="card auth-card">
        <h1>Welcome back</h1>
        <p>Log in to your DietsDNA account.</p>

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <label for="email">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <label class="remember">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                >
                Remember me
            </label>

            <button class="submit" type="submit">Log in</button>
        </form>

        <p class="links">
            <a href="{{ route('password.request') }}">Forgot your password?</a>
        </p>

        <p class="links">
            New to DietsDNA?
            <a href="{{ route('register') }}">Create an account</a>
        </p>
    </section>
@endsection