@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
    <section class="card auth-card">
        <h1>Reset your password</h1>

        <form method="POST" action="{{ url('/reset-password') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                autocomplete="username"
                required
            >

            <label for="password">New password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
            >

            <label for="password_confirmation">Confirm new password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            >

            <button class="submit" type="submit">Reset password</button>
        </form>
    </section>
@endsection