@extends('layouts.app')

@section('title', 'Create an account')

@section('content')
<style>
    .dna-register {
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

    .dna-register,
    .dna-register * {
        box-sizing: border-box;
    }

    .dna-register .dna-story {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 40px;
        padding: 48px;
        background: var(--forest);
        color: var(--cream);
    }

    .dna-register .dna-eyebrow {
        margin: 0 0 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .dna-register .dna-story .dna-eyebrow {
        color: #dbeeb9;
    }

    .dna-register .dna-story h2 {
        margin: 0 0 22px;
        color: var(--cream);
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(36px, 4vw, 48px);
        font-weight: 400;
        line-height: 1.12;
        letter-spacing: -1.5px;
    }

    .dna-register .dna-story h2 em {
        color: #dbeeb9;
        font-weight: 400;
    }

    .dna-register .dna-story-copy {
        margin: 0;
        color: #d0ddce;
        font-size: 15px;
        line-height: 1.8;
    }

    .dna-register .dna-steps {
        display: grid;
        gap: 22px;
        margin: 34px 0 0;
        padding: 0;
        list-style: none;
    }

    .dna-register .dna-steps li {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .dna-register .dna-step-number {
        display: grid;
        place-items: center;
        flex: 0 0 34px;
        height: 34px;
        border: 1px solid #53735c;
        border-radius: 10px;
        color: #dbeeb9;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 17px;
    }

    .dna-register .dna-steps strong {
        display: block;
        margin-bottom: 4px;
        color: var(--cream);
        font-size: 14px;
        font-weight: 600;
    }

    .dna-register .dna-steps p {
        margin: 0;
        color: #c5d3c6;
        font-size: 12px;
        line-height: 1.7;
    }

    .dna-register .dna-story-note {
        margin: 0;
        padding-top: 22px;
        border-top: 1px solid #476750;
        color: #c5d3c6;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        line-height: 1.5;
    }

    .dna-register .dna-form-panel {
        min-width: 0;
        padding: 48px;
    }

    .dna-register .dna-form-panel .dna-eyebrow {
        color: var(--green);
    }

    .dna-register h1 {
        margin: 0 0 12px;
        color: var(--forest);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 38px;
        font-weight: 400;
        line-height: 1.15;
        letter-spacing: -1px;
    }

    .dna-register .dna-intro {
        margin: 0 0 28px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .dna-register .dna-field {
        margin-bottom: 18px;
    }

    .dna-register .dna-field label {
        display: block;
        margin: 0 0 8px;
        color: var(--forest);
        font-size: 13px;
        font-weight: 650;
    }

    .dna-register .dna-field input {
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

    .dna-register .dna-field input:focus {
        border-color: var(--green);
        outline: 3px solid #e4edda;
        outline-offset: 1px;
    }

    .dna-register .dna-field input[aria-invalid="true"] {
        border-color: #a33b32;
    }

    .dna-register .dna-error {
        margin: 7px 0 0;
        color: #a33b32;
        font-size: 12px;
        line-height: 1.6;
    }

    .dna-register .dna-submit {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 14px;
        width: 100%;
        min-height: 52px;
        margin: 24px 0 0;
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

    .dna-register .dna-submit:hover {
        background: var(--green);
    }

    .dna-register a {
        color: var(--green);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .dna-register a:hover {
        color: var(--forest);
    }

    .dna-register .dna-signin {
        margin: 26px 0 0;
        padding-top: 24px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
        text-align: center;
    }

    .dna-register .dna-back {
        display: inline-block;
        margin-top: 22px;
        color: var(--muted);
        font-size: 12px;
        text-decoration: none;
    }

    .dna-register a:focus-visible,
    .dna-register button:focus-visible {
        outline: 3px solid #d9864c;
        outline-offset: 4px;
    }

    @media (max-width: 800px) {
        .dna-register {
            grid-template-columns: 1fr;
            margin: 16px auto;
        }

        .dna-register .dna-story {
            padding: 32px;
            gap: 24px;
        }

        .dna-register .dna-story h2 {
            font-size: 36px;
        }

        .dna-register .dna-steps {
            margin-top: 24px;
            gap: 18px;
        }

        .dna-register .dna-story-note {
            display: none;
        }

        .dna-register .dna-form-panel {
            padding: 32px;
        }
    }

    @media (max-width: 440px) {
        .dna-register .dna-story,
        .dna-register .dna-form-panel {
            padding: 26px 22px;
        }

        .dna-register h1 {
            font-size: 33px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dna-register .dna-submit {
            transition: none;
        }
    }
</style>

<section class="dna-register" aria-labelledby="dna-register-title">
    <aside class="dna-story" aria-label="Getting started with DietsDNA">
        <div>
            <p class="dna-eyebrow">Nutrition that fits your life</p>

            <h2>
                Your goals.<br>
                Your favourite foods.<br>
                <em>Your fresh start.</em>
            </h2>

            <p class="dna-story-copy">
                Build a nutrition routine around the foods you enjoy,
                with practical tools to help you plan ahead.
            </p>

            <ol class="dna-steps">
                <li>
                    <span class="dna-step-number" aria-hidden="true">1</span>
                    <div>
                        <strong>Build your nutrition profile</strong>
                        <p>
                            Set your goals and calculate your personal
                            calorie and macro targets.
                        </p>
                    </div>
                </li>

                <li>
                    <span class="dna-step-number" aria-hidden="true">2</span>
                    <div>
                        <strong>Choose the foods you enjoy</strong>
                        <p>
                            Set your preferences and explore meal ideas
                            that reflect your tastes.
                        </p>
                    </div>
                </li>

                <li>
                    <span class="dna-step-number" aria-hidden="true">3</span>
                    <div>
                        <strong>Bring your week together</strong>
                        <p>
                            Save meals, organise your plan and shopping
                            list, and track your weight progress.
                        </p>
                    </div>
                </li>
            </ol>
        </div>

        <p class="dna-story-note">
            Everyday ingredients.<br>
            A plan shaped around you.
        </p>
    </aside>

    <div class="dna-form-panel">
        <p class="dna-eyebrow">Start your DietsDNA journey</p>

        <h1 id="dna-register-title">Create your account.</h1>

        <p class="dna-intro">
            Your first step towards a more personal nutrition plan.
        </p>

        <form method="POST" action="{{ url('/register') }}">
            @csrf

            <div class="dna-field">
                <label for="name">Full name</label>

                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    required
                    autofocus
                    @error('name')
                        aria-invalid="true"
                        aria-describedby="name-error"
                    @enderror
                >

                @error('name')
                    <p class="dna-error" id="name-error" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

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
                    autocomplete="new-password"
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

            <div class="dna-field">
                <label for="password_confirmation">Confirm password</label>

                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    @error('password_confirmation')
                        aria-invalid="true"
                        aria-describedby="password-confirmation-error"
                    @enderror
                >

                @error('password_confirmation')
                    <p
                        class="dna-error"
                        id="password-confirmation-error"
                        role="alert"
                    >
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <button class="dna-submit" type="submit">
                Create account
                <span aria-hidden="true">↗</span>
            </button>
        </form>

        <p class="dna-signin">
            Already registered?
            <a href="{{ route('login') }}">Log in</a>
        </p>

        <a class="dna-back" href="{{ route('home') }}">
            <span aria-hidden="true">←</span> Back to homepage
        </a>
    </div>
</section>
@endsection