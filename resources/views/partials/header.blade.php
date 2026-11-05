{{-- Public document opening. The shared site footer should close </body> and </html>. --}}
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($seoNoIndex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#153f32">
    <title>{{ $seoTitle ?? 'DietsDNA | Personalised Nutrition' }}</title>
    <meta name="description" content="{{ $seoDescription ?? 'Personalised diet plans, blood test insights, supplement guidance and progress tracking with DietsDNA.' }}">
    @if(!empty($seoKeywords))
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <link rel="canonical" href="{{ $seoUrl ?? url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DietsDNA">
    <meta property="og:locale" content="en_GB">
    <meta property="og:title" content="{{ $seoTitle ?? 'DietsDNA | Personalised Nutrition' }}">
    <meta property="og:description" content="{{ $seoDescription ?? 'Personalised diet plans, blood test insights, supplement guidance and progress tracking with DietsDNA.' }}">
    <meta property="og:url" content="{{ $seoUrl ?? url()->current() }}">
    <meta name="twitter:card" content="summary">
    <style>
        html { scroll-padding-top: 100px; }
        body { margin: 0; background: #faf8f2; }
        .nc-site-header { position: sticky; top: 0; z-index: 1000; background: #faf8f2; border-bottom: 1px solid #e2e7dc; color: #153f32; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .nc-site-header * { box-sizing: border-box; }
        .nc-header-inner { width: min(1220px, calc(100% - 64px)); min-height: 88px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 32px; }
        .nc-brand { display: inline-flex; align-items: center; gap: 11px; flex-shrink: 0; text-decoration: none; color: #153f32; }
        .nc-brand-mark { width: 39px; height: 39px; display: block; }
        .nc-brand-name { font-size: 25px; font-weight: 700; letter-spacing: -1px; line-height: 1; }
        .nc-brand-name span { color: #286449; font-weight: 700; }
        .nc-header-nav { display: flex; align-items: center; gap: 28px; }
        .nc-header-nav a { color: #42564a; text-decoration: none; font-size: 13px; font-weight: 600; line-height: 1.4; padding: 9px 0; transition: color .15s ease; }
        .nc-header-nav a:hover { color: #153f32; }
        .nc-header-nav .nc-account-link { white-space: nowrap; }
        .nc-header-nav .nc-header-cta { padding: 13px 19px; border-radius: 8px; background: #153f32; color: #fff; white-space: nowrap; }
        .nc-header-nav .nc-header-cta:hover { background: #286449; color: #fff; }
        .nc-header-toggle { display: none; align-items: center; justify-content: center; gap: 9px; min-height: 44px; padding: 10px 12px; border: 1px solid #dce2d7; border-radius: 8px; color: #153f32; background: transparent; font: 600 13px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; cursor: pointer; }
        .nc-header-toggle svg { width: 20px; height: 20px; }
        .nc-header-toggle .nc-close-icon { display: none; }
        .nc-header-toggle[aria-expanded="true"] .nc-menu-icon { display: none; }
        .nc-header-toggle[aria-expanded="true"] .nc-close-icon { display: block; }
        .nc-site-header :focus-visible { outline: 3px solid #d9864c; outline-offset: 5px; }
        @media (max-width: 1000px) {
            .nc-header-inner { gap: 20px; }
            .nc-header-nav { gap: 17px; }
            .nc-brand-name { font-size: 23px; }
        }
        @media (max-width: 1080px) {
            .nc-header-inner { min-height: 76px; flex-wrap: wrap; gap: 0; padding: 17px 0; }
            .nc-header-nav { width: 100%; align-items: stretch; flex-direction: column; gap: 0; padding: 16px 0 3px; }
            .nc-header-nav a { padding: 13px 0; border-top: 1px solid #e2e7dc; font-size: 14px; }
            .nc-header-nav .nc-header-cta { margin-top: 12px; text-align: center; border: 0; }
            .nc-site-header.nc-menu-ready .nc-header-toggle { display: inline-flex; }
            .nc-site-header.nc-menu-ready .nc-header-nav { display: none; }
            .nc-site-header.nc-menu-ready.nc-menu-open .nc-header-nav { display: flex; }
        }
        @media (max-width: 580px) {
            .nc-header-inner { width: calc(100% - 32px); }
            .nc-brand-mark { width: 34px; height: 34px; }
            .nc-brand-name { font-size: 23px; }
        }
        @media (prefers-reduced-motion: reduce) { .nc-header-nav a { transition: none; } }
        /* Header refinement */
.nc-site-header {
    background: #faf8f2;
    border-bottom: 1px solid #dfe5d8;
    box-shadow: 0 3px 18px rgba(21, 63, 50, .035);
}

.nc-header-inner {
    width: min(1280px, calc(100% - 64px));
    min-height: 100px;
    gap: 36px;
}

.nc-brand {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.nc-brand-logo {
    display: block;
    width: 210px;
    max-width: 100%;
    height: auto;
}

.nc-header-nav {
    gap: 26px;
}

.nc-header-nav a {
    position: relative;
    color: #42564a;
    font-size: 13px;
    font-weight: 600;
    transition:
        color .18s ease,
        background-color .18s ease,
        border-color .18s ease;
}

.nc-header-nav a:hover {
    color: #153f32;
}

.nc-header-nav > a:not(.nc-account-link):not(.nc-header-cta)::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: 2px;
    height: 2px;
    background: #8da35b;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .18s ease;
}

.nc-header-nav > a:hover::after,
.nc-header-nav > a:focus-visible::after {
    transform: scaleX(1);
}

.nc-header-nav .nc-account-link {
    padding: 12px 17px;
    border: 1px solid #d7dfcf;
    border-radius: 7px;
    color: #153f32;
}

.nc-header-nav .nc-account-link:hover {
    background: #edf1e4;
    border-color: #a8b790;
}

.nc-header-nav .nc-header-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    min-height: 44px;
    padding: 12px 20px;
    border: 1px solid #153f32;
    border-radius: 7px;
    background: #153f32;
    color: #faf8f2;
}

.nc-header-nav .nc-header-cta::after {
    content: "↗";
    color: #dfe9bd;
    font-size: 17px;
    line-height: 1;
}

.nc-header-nav .nc-header-cta:hover {
    background: #245540;
    border-color: #245540;
    color: #fff;
}

.nc-header-toggle {
    background: #f1f4e9;
    border-color: #d7dfcf;
}

.nc-site-header :focus-visible {
    outline: 3px solid #8da35b;
    outline-offset: 5px;
}

/* Forest green header */
.nc-site-header {
    background: #153f32;
    border-bottom: 1px solid #375b46;
    color: #fff;
    box-shadow: 0 3px 18px rgba(0, 0, 0, .06);
}

.nc-brand {
    color: #fff;
}

.nc-header-nav a {
    color: #fff;
}

.nc-header-nav a:hover,
.nc-header-nav a:focus-visible {
    color: #dbeeb9;
}

.nc-header-nav > a:not(.nc-account-link):not(.nc-header-cta)::after {
    background: #dbeeb9;
}

/* Sign in */
.nc-header-nav .nc-account-link {
    border-color: #678574;
    color: #fff;
    background: transparent;
}

.nc-header-nav .nc-account-link:hover {
    border-color: #dbeeb9;
    background: #245442;
    color: #fff;
}

/* Main action */
.nc-header-nav .nc-header-cta {
    border-color: #dbeeb9;
    background: #dbeeb9;
    color: #153f32;
}

.nc-header-nav .nc-header-cta::after {
    color: #153f32;
}

.nc-header-nav .nc-header-cta:hover {
    border-color: #edf2e8;
    background: #edf2e8;
    color: #153f32;
}

/* Mobile menu button */
.nc-header-toggle {
    border-color: #678574;
    background: transparent;
    color: #fff;
}

.nc-header-toggle:hover,
.nc-header-toggle[aria-expanded="true"] {
    background: #245442;
    border-color: #dbeeb9;
}

.nc-site-header :focus-visible {
    outline-color: #dbeeb9;
}

@media (max-width: 1080px) {
    .nc-header-nav a {
        border-top-color: #375b46;
    }

    .nc-header-nav .nc-account-link {
        border-color: #678574;
    }

    .nc-header-nav .nc-account-link:hover {
        border-color: #dbeeb9;
    }
}

@media (max-width: 1080px) {
    .nc-header-inner {
        min-height: 86px;
        padding: 10px 0;
        gap: 0;
    }

    .nc-brand-logo {
        width: 225px;
    }

    .nc-header-nav {
        gap: 0;
        padding: 18px 0 8px;
    }

    .nc-header-nav > a:not(.nc-account-link):not(.nc-header-cta)::after {
        display: none;
    }

    .nc-header-nav > a {
        padding: 15px 2px;
    }

    .nc-header-nav .nc-account-link {
        margin-top: 12px;
        padding: 13px 18px;
        text-align: center;
        border: 1px solid #d7dfcf;
    }

    .nc-header-nav .nc-header-cta {
        margin-top: 10px;
        padding: 13px 20px;
    }
}

@media (max-width: 580px) {
    .nc-header-inner {
        width: calc(100% - 32px);
        min-height: 76px;
    }

    .nc-brand-logo {
        width: 195px;
    }

    .nc-header-toggle {
        min-height: 42px;
        padding: 9px 11px;
    }
}

@media (max-width: 360px) {
    .nc-brand-logo {
        width: 170px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .nc-header-nav a,
    .nc-header-nav a::after {
        transition: none;
    }
}
    </style>
</head>
<body>
    <header class="nc-site-header" id="nc-site-header">
        <div class="nc-header-inner">
            <a class="nc-brand" href="{{ url('/') }}" aria-label="DietsDNA home">
                <img
                    class="nc-brand-logo"
                    src="{{ asset('images/dietsdna-logo-footer.png') }}"
                    alt="DietsDNA"
                    width="260"
                    height="87"
                    loading="eager"
                    decoding="async"
                >
            </a>
            <button class="nc-header-toggle" id="nc-header-toggle" type="button" aria-controls="nc-header-nav" aria-expanded="false" aria-label="Open navigation menu">
                <span>Menu</span>
                <svg class="nc-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg class="nc-close-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
            <nav class="nc-header-nav" id="nc-header-nav" aria-label="Main navigation">
                <a href="{{ url('/') }}#nc-features">Why DietsDNA</a>
                <a href="{{ url('/') }}#nc-how-it-works">How it works</a>
                <a href="{{ url('/') }}#nc-progress">Your progress</a>
                <a href="{{ url('/') }}#nc-faq">FAQs</a>
                @if(session('id'))
                    <a class="nc-header-cta" href="{{ url('/account') }}">My account</a>
                @else
                    <a class="nc-account-link" href="{{ url('/login') }}">Sign in</a>
                    <a class="nc-header-cta" href="{{ url('/register') }}">Get started</a>
                @endif
            </nav>
        </div>
    </header>
    <script>
        (function () {
            var header = document.getElementById('nc-site-header');
            var toggle = document.getElementById('nc-header-toggle');
            var nav = document.getElementById('nc-header-nav');
            if (!header || !toggle || !nav) return;
            function setOpen(open) {
                header.classList.toggle('nc-menu-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            }
            header.classList.add('nc-menu-ready');
            toggle.addEventListener('click', function () {
                setOpen(toggle.getAttribute('aria-expanded') !== 'true');
            });
            nav.addEventListener('click', function (event) {
                var link = event.target.closest('a');
                if (!link) return;
                if (toggle.getAttribute('aria-expanded') === 'true') toggle.focus();
                setOpen(false);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                    setOpen(false);
                    toggle.focus();
                }
            });
            document.addEventListener('click', function (event) {
                if (!header.contains(event.target)) setOpen(false);
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth > 1080) setOpen(false);
            });
        }());
    </script>
