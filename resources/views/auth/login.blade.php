@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<style>
    .dna-login {
        --forest: #153f32;
        --green: #286449;
        --cream: #faf8f2;
        --muted: #6b786f;
        --line: #e2e7dc;

        display: grid;
        grid-template-columns: 1fr 1fr;
        width: min(100%, 1040px);
        margin: 32px auto;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 22px;
        background: #fffefb;
        color: #23382e;
        text-align: left;
        box-shadow: 0 20px 65px rgba(21, 63, 50, .06);
    }

    .dna-login,
    .dna-login * {
        box-sizing: border-box;
    }

    .dna-login .dna-story {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 40px;
        padding: 48px;
        background: var(--forest);
        color: var(--cream);
    }

    .dna-login .dna-eyebrow {
        margin: 0 0 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .dna-login .dna-story .dna-eyebrow {
        color: #dbeeb9;
    }

    .dna-login .dna-story h2 {
        margin: 0 0 22px;
        color: var(--cream);
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(36px, 4vw, 48px);
        font-weight: 400;
        line-height: 1.12;
        letter-spacing: -1.5px;
    }

    .dna-login .dna-story h2 em {
        color: #dbeeb9;
        font-weight: 400;
    }

    .dna-login .dna-story-copy {
        margin: 0;
        color: #d0ddce;
        font-size: 15px;
        line-height: 1.8;
    }

    .dna-login .dna-benefits {
        display: grid;
        gap: 16px;
        margin: 28px 0 0;
        padding: 0;
        list-style: none;
    }

    .dna-login .dna-benefits li {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        line-height: 1.5;
    }

    .dna-login .dna-icon {
        display: grid;
        place-items: center;
        flex: 0 0 34px;
        height: 34px;
        border: 1px solid #53735c;
        border-radius: 10px;
        color: #dbeeb9;
    }

    .dna-login .dna-icon svg {
        width: 18px;
        height: 18px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.6;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .dna-login .dna-story-note {
        margin: 0;
        padding-top: 22px;
        border-top: 1px solid #476750;
        color: #c5d3c6;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        line-height: 1.5;
    }

    .dna-login .dna-form-panel {
        padding: 48px;
    }

    .dna-login .dna-form-panel .dna-eyebrow {
        color: var(--green);
    }

    .dna-login h1 {
        margin: 0 0 12px;
        color: var(--forest);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 40px;
        font-weight: 400;
        line-height: 1.15;
        letter-spacing: -1px;
    }

    .dna-login .dna-intro {
        margin: 0 0 30px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .dna-login .dna-field {
        margin-bottom: 20px;
    }

    .dna-login .dna-field label {
        display: block;
        margin: 0 0 8px;
        color: var(--forest);
        font-size: 13px;
        font-weight: 650;
    }

    .dna-login .dna-field input {
        display: block;
        width: 100%;
        min-height: 50px;
        margin: 0;
        padding: 12px 14px;
        border: 1px solid #d7dfd1;
        border-radius: 8px;
        background: #fff;
        color: #23382e;
        font: inherit;
        font-size: 16px;
        box-shadow: none;
    }

    .dna-login .dna-field input:focus {
        border-color: var(--green);
        outline: 3px solid #e4edda;
        outline-offset: 1px;
    }

    .dna-login .dna-field input[aria-invalid="true"] {
        border-color: #a33b32;
    }

    .dna-login .dna-error {
        margin: 7px 0 0;
        color: #a33b32;
        font-size: 12px;
        line-height: 1.6;
    }

    .dna-login .dna-status {
        margin: 0 0 22px;
        padding: 12px 14px;
        border: 1px solid #d0dfc4;
        border-radius: 8px;
        background: #eef3e6;
        color: var(--forest);
        font-size: 13px;
        line-height: 1.6;
    }

    .dna-login .dna-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin: 4px 0 25px;
        font-size: 12px;
    }

    .dna-login .dna-remember {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--muted);
        font-size: 12px;
        font-weight: 400;
        cursor: pointer;
    }

    .dna-login .dna-remember input {
        width: 16px;
        height: 16px;
        margin: 0;
        accent-color: var(--green);
    }

    .dna-login a {
        color: var(--green);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .dna-login a:hover {
        color: var(--forest);
    }

    .dna-login .dna-submit {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 14px;
        width: 100%;
        min-height: 52px;
        margin: 0;
        padding: 13px 20px;
        border: 1px solid var(--forest);
        border-radius: 8px;
        background: var(--forest);
        color: #fff;
        font: inherit;
        font-size: 14px;
        font-weight: 650;
        cursor: pointer;
        transition: background .2s;
    }

    .dna-login .dna-submit:hover {
        background: var(--green);
    }

    .dna-login .dna-signup {
        margin: 26px 0 0;
        padding-top: 24px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
        text-align: center;
    }

    .dna-login .dna-back {
        display: inline-block;
        margin-top: 22px;
        color: var(--muted);
        font-size: 12px;
        text-decoration: none;
    }

    .dna-login a:focus-visible,
    .dna-login button:focus-visible,
    .dna-login input[type="checkbox"]:focus-visible {
        outline: 3px solid #d9864c;
        outline-offset: 4px;
    }

    @media (max-width: 800px) {
        .dna-login {
            grid-template-columns: 1fr;
            margin: 16px auto;
        }

        .dna-login .dna-story {
            padding: 32px;
            gap: 24px;
        }

        .dna-login .dna-story h2 {
            font-size: 36px;
        }

        .dna-login .dna-benefits {
            gap: 12px;
            margin-top: 22px;
        }

        .dna-login .dna-story-note {
            display: none;
        }

        .dna-login .dna-form-panel {
            padding: 32px;
        }
    }

    @media (max-width: 440px) {
        .dna-login .dna-story,
        .dna-login .dna-form-panel {
            padding: 26px 22px;
        }

        .dna-login h1 {
            font-size: 34px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dna-login .dna-submit {
            transition: none;
        }
    }
</style>

<section class="dna-login" aria-labelledby="dna-login-title">
    <aside class="dna-story" aria-label="Your DietsDNA account">
        <div>
            <p class="dna-eyebrow">Nutrition that fits your life</p>

            <h2>
                Your next chapter.<br>
                <em>One meal at a time.</em>
            </h2>

            <p class="dna-story-copy">
                Return to your plan, find something delicious
                and keep building a routine that works for you.
            </p>

            <ul class="dna-benefits">
                <li>
                    <span class="dna-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="8"/>
                            <circle cx="12" cy="12" r="4"/>
                            <path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        </svg>
                    </span>
                    Personal calorie and macro targets
                </li>

                <li>
                    <span class="dna-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4h16v16H4zM8 4v16M4 9h16M12 13h4m-4 3h4"/>
                        </svg>
                    </span>
                    Your meals, favourites and weekly plan
                </li>

                <li>
                    <span class="dna-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4v16h16M7 15l4-4 4 2 5-7"/>
                            <path d="M16 6h4v4"/>
                        </svg>
                    </span>
                    Shopping lists and weight tracking
                </li>
            </ul>
        </div>

        <p class="dna-story-note">
            Familiar food. A little more structure.<br>
            A plan that feels like yours.
        </p>
    </aside>

    <div class="dna-form-panel">
        <p class="dna-eyebrow">Your DietsDNA account</p>

        <h1 id="dna-login-title">Welcome back.</h1>

        <p class="dna-intro">
            Log in to pick up where you left off.
        </p>

        @if (session('status'))
            <div class="dna-status" role="status">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <div class="dna-field">
                <label for="email">Email address</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="you@example.com"
                    required
                    autofocus
                    @error('email')
                        aria-invalid="true"
                        aria-describedby="email-error"
                    @enderror
                >

                @error('email')
                    <p class="dna-error" id="email-error" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="dna-field">
                <label for="password">Password</label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    @error('password')
                        aria-invalid="true"
                        aria-describedby="password-error"
                    @enderror
                >

                @error('password')
                    <p class="dna-error" id="password-error" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="dna-options">
                <label class="dna-remember" for="remember">
                    <input
                        id="remember"
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                    >
                    Remember me
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">
                        Forgot your password?
                    </a>
                @endif
            </div>

            <button class="dna-submit" type="submit">
                Log in
                <span aria-hidden="true">↗</span>
            </button>
        </form>

        @if (Route::has('register'))
            <p class="dna-signup">
                New to DietsDNA?
                <a href="{{ route('register') }}">Create an account</a>
            </p>
        @endif

        <a class="dna-back" href="{{ route('home') }}">
            <span aria-hidden="true">←</span> Back to homepage
        </a>
    </div>
</section>
@endsection