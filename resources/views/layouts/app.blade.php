@php
    $isAdminArea = request()->routeIs('admin.*');
    $upcomingItems = [];
@endphp

<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153f32">

    <title>@yield('title', 'Your account') | DietsDNA</title>

    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #17332c;
            background: #f4f7f5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
        }

        a {
            color: #167451;
        }

        button,
        input {
            font: inherit;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        summary:focus-visible {
            outline: 3px solid #69b89a;
            outline-offset: 3px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
            text-decoration: none;
        }

        .brand-logo {
            display: block;
            width: 210px;
            max-width: 100%;
            height: auto;
        }

        .skip-link {
            position: fixed;
            top: -100px;
            left: 16px;
            z-index: 2000;
            padding: 12px 16px;
            border-radius: 8px;
            background: #fff;
            color: #153f32;
            font-weight: 600;
        }

        .skip-link:focus {
            top: 12px;
        }

        /* Sidebar */

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            display: flex;
            flex-direction: column;
            width: 260px;
            height: 100dvh;
            overflow-y: auto;
            padding: 26px 14px 20px;
            background: #153f32;
            color: #e7eee8;
        }

        .sidebar-brand {
            padding: 0 4px 26px;
        }

        .sidebar .brand {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e7dc;
            border-radius: 10px;
            background: #faf8f2;
        }

        .sidebar .brand-logo {
            width: 200px;
        }

        .area-label {
            display: block;
            margin: 12px 8px 0;
            color: #b8cfc2;
            font-size: 12px;
            line-height: 1.5;
        }

        .navigation {
            flex: 1;
        }

        .navigation summary {
            display: none;
        }

        .nav-label {
            margin: 10px 12px 14px;
            color: #abc5b6;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .nav-link,
        .nav-pending {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 44px;
            margin-bottom: 5px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-link {
            color: #d4e3d9;
            font-weight: 600;
            transition:
                background-color .15s ease,
                color .15s ease;
        }

        .nav-link:hover {
            background: #245442;
            color: #fff;
        }

        .nav-link.active,
        .nav-link.active:hover {
            background: #f4f1e7;
            color: #153f32;
        }

        .nav-pending {
            color: #b8cfc2;
        }

        .soon {
            padding: 3px 6px;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 4px;
            background: rgba(255, 255, 255, .08);
            color: #b8cfc2;
            font-size: 9px;
        }

        .switch-area {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .13);
        }

        .sidebar-footer {
            margin-top: 28px;
            padding: 20px 8px 0;
            border-top: 1px solid rgba(255, 255, 255, .13);
        }

        .user-name {
            display: block;
            color: #fff;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .user-role {
            display: block;
            margin-top: 6px;
            color: #abc5b6;
            font-size: 11px;
        }

        .sidebar .logout-button {
            width: 100%;
            margin-top: 16px;
            padding: 11px 16px;
            border: 1px solid #547565;
            border-radius: 10px;
            background: transparent;
            color: #fff;
            font-size: 13px;
        }

        .sidebar .logout-button:hover {
            border-color: #789786;
            background: #245442;
        }

        .sidebar a:focus-visible,
        .sidebar button:focus-visible,
        .sidebar summary:focus-visible {
            outline: 3px solid #dbeeb9;
            outline-offset: 2px;
        }

        /* Main area */

        .workspace {
            min-width: 0;
            min-height: 100dvh;
            margin-left: 260px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 80px;
            padding: 22px 36px;
            border-bottom: 1px solid #dde7e1;
            background: #fff;
        }

        .topbar-title {
            font-weight: 700;
        }

        .topbar-context {
            color: #61756a;
            font-size: 13px;
        }

        main {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 36px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(25px, 3vw, 32px);
        }

        p {
            color: #53675e;
            line-height: 1.6;
        }

        .card {
            padding: 28px;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            background: #fff;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 28px;
        }

        .grid h2 {
            margin-top: 0;
            font-size: 19px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            background: #edf3ef;
            color: #53675e;
            font-size: 13px;
        }

        /* Authentication pages */

        .guest-header {
            padding: 16px 24px;
            border-bottom: 1px solid #375b46;
            background: #153f32;
            text-align: center;
        }

        .guest-main {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .auth-card {
            max-width: 460px;
            margin: 16px auto;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: 600;
        }

        input:not([type="checkbox"]):not([type="hidden"]) {
            width: 100%;
            padding: 13px;
            border: 1px solid #b9cbc0;
            border-radius: 8px;
        }

        button {
            padding: 13px 20px;
            border: 0;
            border-radius: 8px;
            background: #167451;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        .submit {
            width: 100%;
            margin-top: 24px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 400;
        }

        .links {
            margin-bottom: 0;
            text-align: center;
        }

        .notice {
            margin-bottom: 20px;
            padding: 14px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .errors {
            background: #fff0ee;
            color: #8b2920;
        }

        .success {
            background: #eaf7ee;
            color: #205c39;
        }

        .errors ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .sidebar .sidebar-brand {
            padding: 4px 12px 26px;
        }
        
        .sidebar .brand {
            display: inline-flex;
            justify-content: flex-start;
            width: auto;
            max-width: 100%;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
        }
        
        .sidebar .brand-logo {
            display: block;
            width: 185px;
            max-width: 100%;
            height: auto;
        }
        
        .sidebar .area-label {
            margin: 12px 0 0;
            color: #b8cfc2;
            font-size: 12px;
        }
        
        @media (max-width: 800px) {
            .sidebar .sidebar-brand {
                padding: 0 0 18px;
            }
        
            .sidebar .brand-logo {
                width: 185px;
            }
        }

        @media (max-width: 1100px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                padding: 20px;
            }

            .sidebar-brand {
                padding: 0 0 18px;
            }

            .sidebar .brand {
                width: auto;
                max-width: 100%;
                padding: 6px 12px;
            }

            .sidebar .brand-logo {
                width: 195px;
            }

            .area-label {
                margin-left: 0;
            }

            .navigation summary {
                display: list-item;
                padding: 12px;
                border-radius: 8px;
                background: #245442;
                color: #fff;
                font-weight: 600;
                cursor: pointer;
            }

            .nav-label {
                margin-top: 20px;
            }

            .sidebar-footer {
                margin-top: 16px;
            }

            .workspace {
                margin-left: 0;
            }

            .topbar {
                padding: 20px;
            }

            .topbar-context {
                display: none;
            }

            main {
                padding: 24px 20px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 24px;
            }
        }

        @media (max-width: 440px) {
            .guest-header {
                padding: 12px 20px;
            }

            .guest-header .brand-logo {
                width: 185px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .nav-link {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to content</a>

    @auth
        <aside class="sidebar" aria-label="Account sidebar">
            <div class="sidebar-brand">
                <a
                    href="{{ $isAdminArea ? route('admin.dashboard') : route('dashboard') }}"
                    class="brand"
                    aria-label="DietsDNA dashboard"
                >
                    <img
                        class="brand-logo"
                        src="{{ asset('images/dietsdna-logo-footer.png') }}"
                        alt="DietsDNA"
                        width="210"
                        height="70"
                        loading="eager"
                        decoding="async"
                    >
                </a>

                <span class="area-label">
                    {{ $isAdminArea ? 'Administration' : 'Your nutrition space' }}
                </span>
            </div>

            <details class="navigation" id="sidebar-navigation" open>
                <summary>Navigation menu</summary>

                <nav aria-label="{{ $isAdminArea ? 'Administration' : 'Customer' }}">
                    <div class="nav-label">
                        {{ $isAdminArea ? 'Manage platform' : 'Your account' }}
                    </div>

                    <a
                        href="{{ $isAdminArea ? route('admin.dashboard') : route('dashboard') }}"
                        class="nav-link {{ request()->routeIs($isAdminArea ? 'admin.dashboard' : 'dashboard') ? 'active' : '' }}"
                        @if(request()->routeIs($isAdminArea ? 'admin.dashboard' : 'dashboard'))
                            aria-current="page"
                        @endif
                    >
                        Dashboard
                    </a>

                    @if($isAdminArea && auth()->user()->is_admin)
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                            @if(request()->routeIs('admin.users.*'))
                                aria-current="page"
                            @endif
                        >
                            Customers
                        </a>

                        <a
                            href="{{ route('admin.ingredients.index') }}"
                            class="nav-link {{ request()->routeIs('admin.ingredients.*') ? 'active' : '' }}"
                            @if(request()->routeIs('admin.ingredients.*'))
                                aria-current="page"
                            @endif
                        >
                            Ingredients
                        </a>

                        <a
                            href="{{ route('admin.recipes.index') }}"
                            class="nav-link {{ request()->routeIs('admin.recipes.*') ? 'active' : '' }}"
                            @if(request()->routeIs('admin.recipes.*'))
                                aria-current="page"
                            @endif
                        >
                            Recipes
                        </a>

                        <a
                            href="{{ route('admin.calculator-settings.edit') }}"
                            class="nav-link {{ request()->routeIs('admin.calculator-settings.*') ? 'active' : '' }}"
                            @if(request()->routeIs('admin.calculator-settings.*'))
                                aria-current="page"
                            @endif
                        >
                            Calculator settings
                        </a>
                    @endif

                    @if(! $isAdminArea)
                        <a
                            href="{{ route('nutrition-profile.edit') }}"
                            class="nav-link {{ request()->routeIs('nutrition-profile.*') ? 'active' : '' }}"
                            @if(request()->routeIs('nutrition-profile.*'))
                                aria-current="page"
                            @endif
                        >
                            Nutrition profile
                        </a>

                        <a
                            href="{{ route('food-preferences.edit') }}"
                            class="nav-link {{ request()->routeIs('food-preferences.*') ? 'active' : '' }}"
                            @if(request()->routeIs('food-preferences.*'))
                                aria-current="page"
                            @endif
                        >
                            Food preferences
                        </a>

                        <a
                            href="{{ route('meal-ideas.index') }}"
                            class="nav-link {{ request()->routeIs('meal-ideas.*') ? 'active' : '' }}"
                            @if(request()->routeIs('meal-ideas.*'))
                                aria-current="page"
                            @endif
                        >
                            Meal ideas
                        </a>

                        <a
                            href="{{ route('saved-meals.index') }}"
                            class="nav-link {{ request()->routeIs('saved-meals.*') ? 'active' : '' }}"
                            @if(request()->routeIs('saved-meals.*'))
                                aria-current="page"
                            @endif
                        >
                            Saved meals
                        </a>

                        <a
                            href="{{ route('meal-plan.index') }}"
                            class="nav-link {{ request()->routeIs('meal-plan.*', 'weekly-meals.*') ? 'active' : '' }}"
                            @if(request()->routeIs('meal-plan.*', 'weekly-meals.*'))
                                aria-current="page"
                            @endif
                        >
                            Meal plan
                        </a>

                        <a
                            href="{{ route('shopping-list.index') }}"
                            class="nav-link {{ request()->routeIs('shopping-list.*') ? 'active' : '' }}"
                            @if(request()->routeIs('shopping-list.*'))
                                aria-current="page"
                            @endif
                        >
                            Shopping list
                        </a>

                        <a
                            href="{{ route('progress.index') }}"
                            class="nav-link {{ request()->routeIs('progress.*') ? 'active' : '' }}"
                            @if(request()->routeIs('progress.*'))
                                aria-current="page"
                            @endif
                        >
                            Progress
                        </a>
                    @endif

                    @foreach($upcomingItems as $item)
                        <div class="nav-pending" aria-disabled="true">
                            <span>{{ $item }}</span>
                            <span class="soon">Soon</span>
                        </div>
                    @endforeach

                    @if(auth()->user()->is_admin)
                        <div class="switch-area">
                            <div class="nav-label">Switch workspace</div>

                            @if($isAdminArea)
                                <a href="{{ route('dashboard') }}" class="nav-link">
                                    Customer dashboard
                                    <span aria-hidden="true">↗</span>
                                </a>
                            @else
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">
                                    Administration
                                    <span aria-hidden="true">↗</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </nav>
            </details>

            <div class="sidebar-footer">
                <strong class="user-name">{{ auth()->user()->name }}</strong>

                <span class="user-role">
                    {{ auth()->user()->is_admin ? 'Administrator' : 'Customer' }}
                </span>

                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="logout-button">Log out</button>
                </form>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <span class="topbar-title">
                    @yield('title', 'Dashboard')
                </span>

                <span class="topbar-context">
                    {{ $isAdminArea ? 'Platform management' : 'Personal nutrition, made simpler' }}
                </span>
            </header>
    @else
            <header class="guest-header">
                <a href="{{ route('home') }}" class="brand" aria-label="DietsDNA home">
                    <img
                        class="brand-logo"
                        src="{{ asset('images/dietsdna-logo-footer.png') }}"
                        alt="DietsDNA"
                        width="210"
                        height="70"
                        loading="eager"
                        decoding="async"
                    >
                </a>
            </header>
    @endauth

    <main id="main-content" class="@guest guest-main @endguest">
        @if(session('status'))
            <div class="notice success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="notice errors" role="alert">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @auth
        </div>

        <script>
            (() => {
                const navigation = document.getElementById('sidebar-navigation');

                if (!navigation) return;

                const mobile = window.matchMedia('(max-width: 800px)');

                const updateNavigation = () => {
                    navigation.open = !mobile.matches;
                };

                updateNavigation();
                mobile.addEventListener('change', updateNavigation);
            })();
        </script>
    @endauth
</body>
</html>