@extends('layouts.app')

@section('title', 'Forgot password')

@section('content')
<style>
    .dna-reset {
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

    .dna-reset,
    .dna-reset * {
        box-sizing: border-box;
    }

    .dna-reset .dna-story {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 40px;
        padding: 48px;
        background: var(--forest);
        color: var(--cream);
    }

    .dna-reset .dna-eyebrow {
        margin: 0 0 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .dna-reset .dna-story .dna-eyebrow {
        color: #dbeeb9;
    }

    .dna-reset .dna-story h2 {
        margin: 0 0 22px;
        color: var(--cream);
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(36px, 4vw, 48px);
        font-weight: 400;
        line-height: 1.12;
        letter-spacing: -1.5px;
    }

    .dna-reset .dna-story h2 em {
        color: #dbeeb9;
        font-weight: 400;
    }

    .dna-reset .dna-story-copy {
        margin: 0;
        color: #d0ddce;
        font-size: 15px;
        line-height: 1.8;
    }

    .dna-reset .dna-icon {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        margin-bottom: 28px;
        border: 1px solid #53735c;
        border-radius: 14px;
        color: #dbeeb9;
    }

    .dna-reset .dna-icon svg {
        width: 26px;
        height: 26px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.6;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .dna-reset .dna-story-note {
        margin: 0;
        padding-top: 22px;
        border-top: 1px solid #476750;
        color: #c5d3c6;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        line-height: 1.5;
    }

    .dna-reset .dna-form-panel {
        min-width: 0;
        padding: 48px;
        align-self: center;
    }

    .dna-reset .dna-form-panel .dna-eyebrow {
        color: var(--green);
    }

    .dna-reset h1 {
        margin: 0 0 16px;
        color: var(--forest);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 38px;
        font-weight: 400;
        line-height: 1.15;
        letter-spacing: -1px;
    }

    .dna-reset .dna-intro {
        margin: 0 0 28px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .dna-reset .dna-field label {
        display: block;
        margin: 0 0 8px;
        color: var(--forest);
        font-size: 13px;
        font-weight: 650;
    }

    .dna-reset .dna-field input {
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

    .dna-reset .dna-field input:focus {
        border-color: var(--green);
        outline: 3px solid #e4edda;
        outline-offset: 1px;
    }

    .dna-reset .dna-field input[aria-invalid="true"] {
        border-color: #a33b32;
    }

    .dna-reset .dna-submit {
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

    .dna-reset .dna-submit:hover {
        background: var(--green);
    }

    .dna-reset .dna-help {
        margin: 16px 0 0;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .dna-reset .dna-signin {
        margin: 26px 0 0;
        padding-top: 24px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
        text-align: center;
    }

    .dna-reset a {
        color: var(--green);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .dna-reset a:hover {
        color: var(--forest);
    }

    .dna-reset .dna-back {
        display: inline-block;
        margin-top: 22px;
        color: var(--muted);
        font-size: 12px;
        text-decoration: none;
    }

    .dna-reset a:focus-visible,
    .dna-reset button:focus-visible {
        outline: 3px solid #d9864c;
        outline-offset: 4px;
    }

    @media (max-width: 800px) {
        .dna-reset {
            grid-template-columns: 1fr;
            margin: 16px auto;
        }

        .dna-reset .dna-story,
        .dna-reset .dna-form-panel {
            padding: 32px;
        }

        .dna-reset .dna-story {
            gap: 24px;
        }

        .dna-reset .dna-story h2 {
            font-size: 36px;
        }

        .dna-reset .dna-story-note {
            display: none;
        }
    }

    @media (max-width: 440px) {
        .dna-reset .dna-story,
        .dna-reset .dna-form-panel {
            padding: 26px 22px;
        }

        .dna-reset h1 {
            font-size: 33px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dna-reset .dna-submit {
            transition: none;
        }
    }
</style>

<section class="dna-reset" aria-labelledby="dna-reset-title">
    <aside class="dna-story" aria-label="Account recovery">
        <div>
            <p class="dna-eyebrow">Your DietsDNA account</p>

            <span class="dna-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <rect x="5" y="10" width="14" height="11" rx="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    <circle cx="12" cy="15" r="1"/>
                    <path d="M12 16v2"/>
                </svg>
            </span>

            <h2>
                A fresh password.<br>
                <em>Back to your plan.</em>
            </h2>

            <p class="dna-story-copy">
                Forgotten your password? Request a reset link
                using the email address you registered with.
            </p>
        </div>

        <p class="dna-story-note">
            Your next step starts<br>
            right where you left off.
        </p>
    </aside>

    <div class="dna-form-panel">
        <p class="dna-eyebrow">Let’s get you back in</p>

        <h1 id="dna-reset-title">Forgot your password?</h1>

        <p class="dna-intro" id="dna-reset-description">
            Enter your account email address to request
            a link to set a new password.
        </p>

        <form method="POST" action="{{ url('/forgot-password') }}">
            @csrf

            <div class="dna-field">
                <label for="email">Email address</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="you@example.com"
                    aria-describedby="dna-reset-description"
                    required
                    autofocus
                    @error('email')
                        aria-invalid="true"
                    @enderror
                >
            </div>

            <button class="dna-submit" type="submit">
                Send reset link
                <span aria-hidden="true">↗</span>
            </button>
        </form>

        <p class="dna-help">
            If you receive a reset email, follow its link to choose
            a new password. Check your spam or junk folder too.
        </p>

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