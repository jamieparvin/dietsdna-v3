@extends('layouts.app')

@section('title', 'Administration')

@section('content')
@php
    $draftLabels = [
        'queued' => 'Waiting to start',
        'generating' => 'Generating',
        'ready' => 'Ready for review',
        'confirmed' => 'Confirmed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];
@endphp

<style>
    .ad-page {
        width:100%;
        color:#183d32;
    }

    .ad-page * {
        box-sizing:border-box;
    }

    .ad-page a:focus-visible {
        outline:3px solid #b79044;
        outline-offset:4px;
    }

    .ad-hero {
        display:flex;
        align-items:center;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:24px;
        padding:30px 34px;
        margin-bottom:24px;
        border-radius:20px;
        background:linear-gradient(115deg,#153f31,#237450);
        color:#fff;
    }

    .ad-eyebrow {
        margin:0 0 10px;
        font-size:11px;
        font-weight:700;
        letter-spacing:1.5px;
        text-transform:uppercase;
        color:#c3d8c5;
    }

    .ad-hero h1 {
        margin:0 0 10px;
        font-size:30px;
        letter-spacing:-.6px;
        line-height:1.3;
    }

    .ad-hero p:last-child {
        margin:0;
        color:#dce9de;
        font-size:14px;
        line-height:1.7;
    }

    .ad-button {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:44px;
        padding:11px 16px;
        border:1px solid #d5e0d8;
        border-radius:9px;
        background:#fff;
        color:#234d37;
        font-size:13px;
        font-weight:650;
        text-decoration:none;
        text-align:center;
    }

    .ad-hero .ad-button {
        background:#f4f1e7;
        border-color:#f4f1e7;
    }

    .ad-stats {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:15px;
        margin-bottom:24px;
    }

    .ad-stat,
    .ad-panel,
    .ad-shortcut {
        min-width:0;
        border:1px solid #dfe7e1;
        border-radius:15px;
        background:#fff;
    }

    .ad-stat {
        padding:22px;
    }

    .ad-stat-label {
        display:flex;
        align-items:center;
        gap:8px;
        color:#50665a;
        font-size:13px;
        font-weight:650;
    }

    .ad-dot {
        width:8px;
        height:8px;
        flex-shrink:0;
        border-radius:50%;
        background:var(--ad-color,#19815a);
    }

    .ad-stat-value {
        display:block;
        margin:14px 0 8px;
        font-size:32px;
        line-height:1.2;
        letter-spacing:-.6px;
    }

    .ad-muted {
        margin:0;
        color:#6b7d71;
        font-size:12px;
        line-height:1.7;
    }

    .ad-section-heading {
        margin:27px 0 15px;
        font-size:20px;
        letter-spacing:-.3px;
    }

    .ad-shortcuts {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:15px;
        margin-bottom:24px;
    }

    .ad-shortcut {
        display:block;
        padding:21px;
        color:#183d32;
        text-decoration:none;
        transition:border-color .15s,background .15s;
    }

    .ad-shortcut:hover {
        border-color:#adc5b4;
        background:#fafcf8;
    }

    .ad-shortcut h3 {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        margin:0 0 10px;
        font-size:16px;
    }

    .ad-shortcut p {
        margin:0;
        color:#6b7d71;
        font-size:12px;
        line-height:1.7;
    }

    .ad-arrow {
        color:#789582;
        font-size:18px;
    }

    .ad-main {
        display:grid;
        grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);
        gap:20px;
        align-items:start;
    }

    .ad-panel {
        padding:23px;
    }

    .ad-panel + .ad-panel {
        margin-top:20px;
    }

    .ad-panel-header {
        display:flex;
        align-items:center;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:12px;
        margin-bottom:19px;
    }

    .ad-panel-header h2 {
        margin:0;
        font-size:19px;
        letter-spacing:-.3px;
    }

    .ad-panel-header a,
    .ad-text-link {
        display:inline-flex;
        align-items:center;
        min-height:44px;
        color:#315e43;
        font-size:12px;
        font-weight:650;
        text-underline-offset:3px;
    }

    .ad-list {
        list-style:none;
        margin:0;
        padding:0;
    }

    .ad-row {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:15px;
        padding:16px 0;
        border-top:1px solid #edf1eb;
    }

    .ad-row > div {
        min-width:0;
    }

    .ad-row strong {
        display:block;
        margin-bottom:5px;
        font-size:14px;
        line-height:1.5;
        overflow-wrap:anywhere;
    }

    .ad-row .ad-muted {
        overflow-wrap:anywhere;
    }

    .ad-badge {
        display:inline-block;
        padding:5px 9px;
        border-radius:20px;
        background:#edf4ed;
        color:#44664e;
        font-size:11px;
        line-height:1.5;
        text-align:center;
        flex-shrink:0;
    }

    .ad-badge-failed {
        background:#fff0eb;
        color:#984b3e;
    }

    .ad-badge-queued,
    .ad-badge-generating {
        background:#fff5df;
        color:#896726;
    }

    .ad-checks {
        list-style:none;
        padding:0;
        margin:0;
    }

    .ad-checks li {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:15px;
        padding:13px 0;
        border-top:1px solid #edf1eb;
        font-size:13px;
        color:#50665a;
    }

    .ad-checks strong {
        color:#183d32;
        font-size:16px;
    }

    .ad-empty {
        padding:22px 16px;
        border-radius:10px;
        background:#f7f9f4;
        color:#6b7d71;
        font-size:13px;
        line-height:1.7;
    }

    .ad-note {
        margin:15px 0 0;
        color:#758477;
        font-size:11px;
        line-height:1.8;
    }

    @media(max-width:1100px) {
        .ad-stats,
        .ad-shortcuts {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .ad-main {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:550px) {
        .ad-hero {
            padding:24px 20px;
            border-radius:16px;
        }

        .ad-hero h1 {
            font-size:25px;
        }

        .ad-stats,
        .ad-shortcuts {
            gap:10px;
        }

        .ad-stat {
            padding:17px 13px;
        }

        .ad-stat-label {
            font-size:12px;
        }

        .ad-stat-value {
            font-size:28px;
        }

        .ad-shortcut {
            padding:17px 13px;
        }

        .ad-panel {
            padding:19px 16px;
        }

        .ad-row {
            flex-wrap:wrap;
        }
    }
</style>

<div class="ad-page">
    <header class="ad-hero">
        <div>
            <p class="ad-eyebrow">Platform overview</p>
            <h1>DietsDNA administration</h1>
            <p>
                Welcome, {{ auth()->user()->name }}.
                Your customers, ingredient library and weekly generation activity.
            </p>
        </div>

        <a class="ad-button" href="{{ route('dashboard') }}">
            Open customer dashboard →
        </a>
    </header>

    <section class="ad-stats" aria-label="Platform statistics">
        <article class="ad-stat">
            <div class="ad-stat-label">
                <span class="ad-dot" aria-hidden="true"></span>
                Customers
            </div>

            <strong class="ad-stat-value">
                {{ number_format($customerCount) }}
            </strong>

            <p class="ad-muted">
                {{ number_format($newCustomerCount) }} registered in the last 7 days.
                Administrator accounts excluded.
            </p>
        </article>

        <article class="ad-stat" style="--ad-color:#6688ae">
            <div class="ad-stat-label">
                <span class="ad-dot" aria-hidden="true"></span>
                Active ingredients
            </div>

            <strong class="ad-stat-value">
                {{ number_format($activeIngredientCount) }}
            </strong>

            <p class="ad-muted">
                {{ number_format($inactiveIngredientCount) }} inactive records retained.
            </p>
        </article>

        <article class="ad-stat" style="--ad-color:#c49a51">
            <div class="ad-stat-label">
                <span class="ad-dot" aria-hidden="true"></span>
                Weekly requests in progress
            </div>

            <strong class="ad-stat-value">
                {{ number_format($queuedCount + $generatingCount) }}
            </strong>

            <p class="ad-muted">
                {{ number_format($queuedCount) }} waiting ·
                {{ number_format($generatingCount) }} generating
            </p>
        </article>

        <article class="ad-stat" style="--ad-color:#a77997">
            <div class="ad-stat-label">
                <span class="ad-dot" aria-hidden="true"></span>
                Previews ready for review
            </div>

            <strong class="ad-stat-value">
                {{ number_format($readyCount) }}
            </strong>

            <p class="ad-muted">
                Awaiting a customer’s confirmation or discard.
            </p>
        </article>
    </section>

    <h2 class="ad-section-heading">Manage your platform</h2>

    <nav class="ad-shortcuts" aria-label="Administration shortcuts">
        <a class="ad-shortcut" href="{{ route('admin.users.index') }}">
            <h3>
                Customers
                <span class="ad-arrow" aria-hidden="true">↗</span>
            </h3>
            <p>View registered accounts and customer details.</p>
        </a>

        <a class="ad-shortcut" href="{{ route('admin.ingredients.index') }}">
            <h3>
                Ingredients
                <span class="ad-arrow" aria-hidden="true">↗</span>
            </h3>
            <p>Manage food records, availability and nutrition per 100 g.</p>
        </a>

        <a class="ad-shortcut" href="{{ route('admin.ingredients.import.create') }}">
            <h3>
                Import ingredients
                <span class="ad-arrow" aria-hidden="true">↗</span>
            </h3>
            <p>Upload a spreadsheet and review its contents before importing.</p>
        </a>

        <a class="ad-shortcut" href="{{ route('admin.calculator-settings.edit') }}">
            <h3>
                Calculator settings
                <span class="ad-arrow" aria-hidden="true">↗</span>
            </h3>
            <p>Review global calorie and macro settings.</p>
        </a>
    </nav>

    <div class="ad-main">
        <section class="ad-panel">
            <header class="ad-panel-header">
                <h2>Recent weekly requests</h2>
                <span class="ad-muted">Latest six requests</span>
            </header>

            <ul class="ad-list">
                @forelse($recentDrafts as $draft)
                    <li class="ad-row">
                        <div>
                            <strong>
                                {{ $draft->customer_name ?? 'Deleted account' }}
                            </strong>

                            <p class="ad-muted">
                                Week of {{ $draft->start_date->format('j M Y') }}
                                · Request #{{ $draft->id }}
                            </p>

                            <p class="ad-muted">
                                Requested
                                {{ $draft->created_at->timezone('Europe/London')->format('j M Y, H:i') }}
                            </p>
                        </div>

                        <span class="ad-badge ad-badge-{{ $draft->status }}">
                            {{ $draftLabels[$draft->status] ?? ucfirst($draft->status) }}
                        </span>
                    </li>
                @empty
                    <li class="ad-empty">
                        No weekly generation requests yet.
                    </li>
                @endforelse
            </ul>

            <p class="ad-note">
                Confirmed records show previous confirmation activity.
                Customers may subsequently change or clear those plans.
            </p>
        </section>

        <div>
            <section class="ad-panel">
                <header class="ad-panel-header">
                    <h2>Library and generation checks</h2>
                </header>

                <ul class="ad-checks">
                    <li>
                        <span>Active ingredients with missing nutrition</span>
                        <strong>{{ number_format($missingNutritionCount) }}</strong>
                    </li>

                    <li>
                        <span>Failed weekly requests in the last 7 days</span>
                        <strong>{{ number_format($failedLastSevenDays) }}</strong>
                    </li>
                </ul>

                <p class="ad-note">
                    Missing nutrition means at least one nutrient is blank.
                    Failure counts include historical attempts, even if a later
                    request succeeded.
                </p>

                <a class="ad-text-link" href="{{ route('admin.ingredients.index') }}">
                    Review ingredient library →
                </a>
            </section>

            <section class="ad-panel">
                <header class="ad-panel-header">
                    <h2>New customers</h2>
                    <a href="{{ route('admin.users.index') }}">View customers →</a>
                </header>

                <ul class="ad-list">
                    @forelse($recentCustomers as $customer)
                        <li class="ad-row">
                            <div>
                                <strong>{{ $customer->name }}</strong>
                                <p class="ad-muted">{{ $customer->email }}</p>
                                <p class="ad-muted">
                                    Joined
                                    {{ $customer->created_at->timezone('Europe/London')->format('j M Y') }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="ad-empty">
                            No customer accounts yet.
                            Administrator accounts are excluded.
                        </li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection