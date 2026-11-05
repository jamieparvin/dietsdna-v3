@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
<style>
    .dna-new-password {
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

    .dna-new-password,
    .dna-new-password * {
        box-sizing: border-box;
    }

    .dna-new-password .dna-story {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 40px;
        padding: 48px;
        background: var(--forest);
        color: var(--cream);
    }

    .dna-new-password .dna-eyebrow {
        margin: 0 0 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .dna-new-password .dna-story .dna-eyebrow {
        color: #dbeeb9;
    }

    .dna-new-password .dna-story h2 {
        margin: 0 0 22px;
        color: var(--cream);
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(36px, 4vw, 48px);
        font-weight: 400;
        line-height: 1.12;
        letter-spacing: -1.5px;
    }

    .dna-new-password .dna-story h2 em {
        color: #dbeeb9;
        font-weight: 400;
    }

    .dna-new-password .dna-story-copy {
        margin: 0;
        color: #d0ddce;
        font-size: 15px;
        line-height: 1.8;
    }

    .dna-new-password .dna-icon {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        margin-bottom: 28px;
        border: 1px solid #53735c;
        border-radius: 14px;
        color: #dbeeb9;
    }

    .dna-new-password .dna-icon svg {
        width: 26px;
        height: 26px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.6;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .dna-new-password .dna-story-note {
        margin: 0;
        padding-top: 22px;
        border-top: 1px solid #476750;
        color: #c5d3c6;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        line-height: 1.5;
    }

    .dna-new-password .dna-form-panel {
        min-width: 0;
        padding: 48px;
        align-self: center;
    }

    .dna-new-password .dna-form-panel .dna-eyebrow {
        color: var(--green);
    }

    .dna-new-password h1 {
        margin: 0 0 16px;
        color: var(--forest);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 38px;
        font-weight: 400;
        line-height: 1.15;
        letter-spacing: -1px;
    }

    .dna-new-password .dna-intro {
        margin: 0 0 28px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .dna-new-password .dna-field {
        margin-bottom: 18px;
    }

    .dna-new-password .dna-field label {
        display: block;
        margin: 0 0 8px;
        color: var(--forest);
        font-size: 13px;
        font-weight: 650;
    }

    .dna-new-password .dna-field input {
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

    .dna-new-password .dna-field input:focus {
        border-color: var(--green);
        outline: 3px solid #e4edda;
        outline-offset: 1px;
    }

    .dna-new-password .dna-field input[aria-invalid="true"] {
        border-color: #a33b32;
    }

    .dna-new-password .dna-help {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .dna-new-password .dna-submit {
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

    .dna-new-password .dna-submit:hover {
        background: var(--green);
    }

    .dna-new-password a {
        color: var(--green);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .dna-new-password a:hover {
        color: var(--forest);
    }

    .dna-new-password .dna-signin {
        margin: 26px 0 0;
        padding-top: 24px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
        text-align: center;
    }

    .dna-new-password .dna-back {
        display: inline-block;
        margin-top: 22px;
        color: var(--muted);
        font-size: 12px;
        text-decoration: none;
    }

    .dna-new-password a:focus-visible,
    .dna-new-password button:focus-visible {
        outline: 3px solid #d9864c;
        outline-offset: 4px;
    }

    @media (max-width: 800px) {
        .dna-new-password {
            grid-template-columns: 1fr;
            margin: 16px auto;
        }

        .dna-new-password .dna-story,
        .dna-new-password .dna-form-panel {
            padding: 32px;
        }

        .dna-new-password .dna-story {
            gap: 24px;
        }

        .dna-new-password .dna-story h2 {
            font-size: 36px;
        }

        .dna-new-password .dna-story-note {
            display: none;
        }
    }

    @media (max-width: 440px) {
        .dna-new-password .dna-story,
        .dna-new-password .dna-form-panel {
            padding: 26px 22px;
        }

        .dna-new-password h1 {
            font-size: 33px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dna-new-password .dna-submit {
            transition: none;
        }
    }
</style>

<section class="dna-new-password" aria-labelledby="dna-reset-title">
    <aside class="dna-story" aria-label="Choose a new password">
        <div>
            <p class="dna-eyebrow">Your DietsDNA account</p>

            <span class="dna-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <rect x="5" y="10" width="14" height="11" rx="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    <path d="m9 15 2 2 4-4"/>
                </svg>
            </span>

            <h2>
                A new password.<br>
                <em>Your next step.</em>
            </h2>

            <p class="dna-story-copy">
                Choose a new password for your account,
                then return to your meals, goals and weekly plan.
            </p>
        </div>

        <p class="dna-story-note">
            Your favourites. Your routine.<br>
            Ready when you are.
        </p>
    </aside>

    <div class="dna-form-panel">
        <p class="dna-eyebrow">Account recovery</p>

        <h1 id="dna-reset-title">Reset your password.</h1>

        <p class="dna-intro">
            Enter your account email and choose a new password.
        </p>

        <form method="POST" action="{{ url('/reset-password') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="dna-field">
                <label for="email">Email address</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="username"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    @error('email')
                        aria-invalid="true"
                    @enderror
                >
            </div>

            <div class="dna-field">
                <label for="password">New password</label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    aria-describedby="dna-password-help"
                    required
                    @error('password')
                        aria-invalid="true"
                    @enderror
                >

                <p class="dna-help" id="dna-password-help">
                    Choose a unique password you do not use for another account.
                </p>
            </div>

            <div class="dna-field">
                <label for="password_confirmation">Confirm new password</label>

                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    @error('password_confirmation')
                        aria-invalid="true"
                    @enderror
                >
            </div>

            <button class="dna-submit" type="submit">
                Reset password
                <span aria-hidden="true">↗</span>
            </button>
        </form>

        <p class="dna-signin">
            Remember your password?
            <a href="{{ route('login') }}">Log in</a>
        </p>

        <a class="dna-back" href="{{ route('home') }}">
            <span aria-hidden="true">←</span> Back to homepage
        </a>
    </div>
</section>
@endsection