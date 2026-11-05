{{-- Paired with common.navigations.site_header, which opens the HTML document. --}}
<style>
    .nc-site-footer {
        background: #153f32;
        color: #edf2e8;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 14px;
        line-height: 1.65;
    }

    .nc-site-footer * {
        box-sizing: border-box;
    }

    .nc-site-footer a {
        text-decoration: none;
    }

    .nc-footer-wrap {
        width: min(1280px, calc(100% - 64px));
        margin: 0 auto;
    }

    .nc-footer-top {
        display: grid;
        grid-template-columns: 1.4fr 1fr 1fr;
        gap: 64px;
        padding: 64px 0 48px;
    }

    .nc-footer-top > * {
        min-width: 0;
    }

    .nc-footer-brand {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
    }

    .nc-footer-logo {
        display: block;
        width: 210px;
        max-width: 100%;
        height: auto;
    }

    .nc-footer-description {
        max-width: 350px;
        margin: 20px 0 0;
        color: #c5d5c9;
        font-size: 14px;
        line-height: 1.8;
    }

    .nc-footer-tagline {
        margin: 19px 0 0;
        color: #dbeeb9;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        line-height: 1.4;
        letter-spacing: -.3px;
    }

    .nc-footer-heading {
        margin: 0 0 18px;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.6;
        letter-spacing: 1.4px;
        text-transform: uppercase;
    }

    .nc-footer-links {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .nc-footer-links a {
        display: inline-block;
        padding: 5px 0;
        color: #c5d5c9;
        line-height: 1.6;
        transition: color .18s ease;
    }

    .nc-footer-links a:hover {
        color: #dbeeb9;
        text-decoration: underline;
        text-underline-offset: 5px;
        text-decoration-color: #a8844f;
    }

    .nc-footer-soon {
        display: inline-block;
        margin-left: 5px;
        color: #dbeeb9;
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .nc-footer-status {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 7px 12px;
        border: 1px solid #52725e;
        border-radius: 99px;
        color: #e5f0d2;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.4;
    }

    .nc-footer-status-dot {
        width: 6px;
        height: 6px;
        flex-shrink: 0;
        border-radius: 50%;
        background: #dbeeb9;
        box-shadow: 0 0 0 3px rgba(219, 238, 185, .12);
    }

    .nc-footer-status-copy {
        max-width: 280px;
        margin: 16px 0 0;
        color: #c5d5c9;
        font-size: 13px;
        line-height: 1.8;
    }

    .nc-footer-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        min-height: 44px;
        margin-top: 21px;
        padding: 10px 16px;
        border: 1px solid #67806a;
        border-radius: 7px;
        color: #dbeeb9;
        font-size: 12px;
        font-weight: 600;
        transition:
            background-color .18s ease,
            border-color .18s ease,
            color .18s ease;
    }

    .nc-footer-cta:hover {
        border-color: #dbeeb9;
        background: #dbeeb9;
        color: #153f32;
    }

    .nc-footer-cta svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .nc-footer-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 21px 0;
        border-top: 1px solid #375b46;
    }

    .nc-footer-copyright {
        margin: 0;
        color: #bacbbf;
        font-size: 12px;
    }

    .nc-footer-top-link {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        min-height: 44px;
        padding: 8px 0;
        color: #dbeeb9;
        font-size: 12px;
        font-weight: 600;
    }

    .nc-footer-top-link:hover {
        color: #fff;
    }

    .nc-footer-top-link svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .nc-site-footer :focus-visible {
        outline: 3px solid #dbeeb9;
        outline-offset: 5px;
    }

    @media (max-width: 1080px) {
        .nc-footer-top {
            gap: 36px;
            grid-template-columns: 1.25fr 1fr 1fr;
        }

        .nc-footer-logo {
            width: 245px;
        }
    }

    @media (max-width: 860px) {
        .nc-footer-top {
            grid-template-columns: 1fr 1fr;
            gap: 38px;
            padding: 48px 0 38px;
        }

        .nc-footer-about {
            grid-column: 1 / -1;
        }

        .nc-footer-logo {
            width: 280px;
        }

        .nc-footer-description {
            max-width: 480px;
        }
    }

    @media (max-width: 580px) {
        .nc-footer-wrap {
            width: calc(100% - 32px);
        }

        .nc-footer-top {
            grid-template-columns: 1fr;
            gap: 34px;
            padding: 38px 0 30px;
        }

        .nc-footer-about {
            grid-column: auto;
        }

        .nc-footer-logo {
            width: 235px;
        }

        .nc-footer-description {
            margin-top: 16px;
        }

        .nc-footer-tagline {
            font-size: 19px;
        }

        .nc-footer-heading {
            margin-bottom: 13px;
        }

        .nc-footer-links a {
            padding: 8px 0;
        }

        .nc-footer-status-copy {
            max-width: 350px;
        }

        .nc-footer-bottom {
            align-items: flex-start;
            flex-direction: column;
            gap: 6px;
            padding: 20px 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .nc-footer-links a,
        .nc-footer-cta {
            transition: none;
        }
    }
</style>

<footer class="nc-site-footer">
    <div class="nc-footer-wrap">
        <div class="nc-footer-top">

            {{-- Brand and introduction --}}
            <div class="nc-footer-about">
                <a
                    class="nc-footer-brand"
                    href="{{ url('/') }}"
                    aria-label="DietsDNA home"
                >
                    <img
                        class="nc-footer-logo"
                        src="{{ asset('images/dietsdna-logo-footer.png') }}"
                        alt="DietsDNA"
                        width="280"
                        height="94"
                        loading="lazy"
                        decoding="async"
                    >
                </a>

                <p class="nc-footer-description">
                    Personalised nutrition plans, meal ideas and progress
                    tracking—shaped around your profile, goals and the foods
                    you enjoy.
                </p>

                <p class="nc-footer-tagline">
                    Your health profile. Your nutrition plan.
                </p>
            </div>

            {{-- Navigation --}}
            <nav aria-label="Footer navigation">
                <h2 class="nc-footer-heading">Explore DietsDNA</h2>

                <ul class="nc-footer-links">
                    <li>
                        <a href="{{ url('/') }}#nc-features">
                            The DietsDNA approach
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-how-it-works">
                            How it works
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-blood-results">
                            Blood test insights
                            <span class="nc-footer-soon">(coming soon)</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-supplements">
                            Supplement guidance
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-food-preferences">
                            Food preferences
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-progress">
                            Your progress
                        </a>
                    </li>
                    <li>
                        <a href="{{ url('/') }}#nc-faq">
                            FAQs
                        </a>
                    </li>
                </ul>
            </nav>

            {{-- Account action --}}
            <div>
                <h2 class="nc-footer-heading">
                    A plan that fits your life
                </h2>

                <span class="nc-footer-status">
                    <span
                        class="nc-footer-status-dot"
                        aria-hidden="true"
                    ></span>
                    Personalised for you
                </span>

                <p class="nc-footer-status-copy">
                    Set your goals, choose the foods you enjoy and bring your
                    week together with practical meal plans, shopping lists
                    and progress tracking.
                </p>

                @if(session('id'))
                    <a class="nc-footer-cta" href="{{ url('/account') }}">
                        <span>My account</span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M7 17 17 7M7 7h10v10"/>
                        </svg>
                    </a>
                @else
                    <a class="nc-footer-cta" href="{{ url('/register') }}">
                        <span>Create your account</span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M7 17 17 7M7 7h10v10"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        <div class="nc-footer-bottom">
            <p class="nc-footer-copyright">
                &copy; {{ date('Y') }} DietsDNA. All rights reserved.
            </p>

            <a class="nc-footer-top-link" href="#nc-site-header">
                Back to top
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M12 19V5m-6 6 6-6 6 6"/>
                </svg>
            </a>
        </div>
    </div>
</footer>
</body>
</html>