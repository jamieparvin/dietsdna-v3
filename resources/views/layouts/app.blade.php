@php
    $isAdminArea = request()->routeIs('admin.*');

    $upcomingItems = $isAdminArea
        ? ['Meal plans', 'Imports', 'Settings']
        : ['Meal plan', 'Recipes', 'Shopping list', 'Food diary', 'Progress'];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'DietsDNA') | DietsDNA</title>

    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #17332c;
            background: #f4f7f5;
        }

        * { box-sizing: border-box; }

        body { margin: 0; }

        a { color: #167451; }

        button, input { font: inherit; }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        summary:focus-visible {
            outline: 3px solid #69b89a;
            outline-offset: 3px;
        }

        .brand {
            display: inline-block;
            color: #17332c;
            font-size: 26px;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span { color: #16805b; }

        .skip-link {
            position: fixed;
            top: -100px;
            left: 16px;
            z-index: 100;
            padding: 12px;
            background: white;
        }

        .skip-link:focus { top: 12px; }

        /* Sidebar */

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 260px;
            height: 100dvh;
            overflow-y: auto;
            background: white;
            border-right: 1px solid #dde7e1;
            display: flex;
            flex-direction: column;
            padding: 30px 20px 20px;
        }

        .sidebar-brand { padding: 0 12px 28px; }

        .area-label {
            display: block;
            margin-top: 8px;
            color: #61756a;
            font-size: 13px;
        }

        .navigation { flex: 1; }

        .navigation summary { display: none; }

        .nav-label {
            margin: 10px 12px 14px;
            color: #61756a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .nav-link,
        .nav-pending {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 13px 14px;
            margin-bottom: 5px;
            border-radius: 9px;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-link {
            color: #344d40;
            font-weight: 600;
        }

        .nav-link:hover { background: #f0f6f2; }

        .nav-link.active {
            background: #e7f3ec;
            color: #126441;
        }

        .nav-pending { color: #61756a; }

        .soon {
            padding: 3px 6px;
            background: #f0f3f1;
            color: #61756a;
            border-radius: 4px;
            font-size: 10px;
        }

        .switch-area {
            margin-top: 22px;
            border-top: 1px solid #e5ece7;
            padding-top: 16px;
        }

        .sidebar-footer {
            margin-top: 28px;
            padding: 18px 12px 0;
            border-top: 1px solid #e5ece7;
        }

        .user-name {
            display: block;
            overflow-wrap: anywhere;
            font-size: 14px;
        }

        .user-role {
            display: block;
            margin-top: 6px;
            color: #61756a;
            font-size: 12px;
        }

        .logout-button {
            margin-top: 16px;
            width: 100%;
            background: #f0f5f2;
            color: #294d3a;
        }

        /* Main area */

        .workspace {
            margin-left: 260px;
            min-height: 100dvh;
            min-width: 0;
        }

        .topbar {
            min-height: 80px;
            background: white;
            border-bottom: 1px solid #dde7e1;
            padding: 22px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .topbar-title { font-weight: 700; }

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
            background: white;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            padding: 28px;
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
            background: #edf3ef;
            color: #53675e;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
        }

        /* Authentication pages */

        .guest-header {
            background: white;
            border-bottom: 1px solid #dde7e1;
            padding: 24px;
            text-align: center;
        }

        .guest-main { max-width: 1100px; }

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
            border: 0;
            border-radius: 8px;
            padding: 13px 20px;
            background: #167451;
            color: white;
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
            text-align: center;
            margin-bottom: 0;
        }

        .notice {
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
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
            padding-left: 20px;
            margin: 0;
        }

        /* Version 2 sidebar appearance */

        .sidebar {
            background: #153f32;
            border-right: 0;
            padding: 26px 14px 20px;
            color: #e7eee8;
        }

        .sidebar-brand {
            padding: 0 12px 26px;
        }

        .sidebar .brand {
            color: #ffffff;
            font-size: 25px;
            letter-spacing: -.8px;
        }

        .sidebar .brand span {
            color: #dce5b4;
        }

        .sidebar .area-label {
            color: #b8cfc2;
            font-size: 12px;
        }

        .sidebar .nav-label {
            color: #abc5b6;
            font-size: 10px;
            letter-spacing: 1.5px;
        }

        .sidebar .nav-link,
        .sidebar .nav-pending {
            min-height: 44px;
            padding: 12px 14px;
            margin-bottom: 5px;
            border-radius: 10px;
            font-size: 14px;
        }

        .sidebar .nav-link {
            color: #d4e3d9;
            font-weight: 600;
            transition: background-color .15s ease, color .15s ease;
        }

        .sidebar .nav-link:hover {
            background: #245442;
            color: #ffffff;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link.active:hover {
            background: #f4f1e7;
            color: #153f32;
        }

        .sidebar .nav-pending {
            color: #b8cfc2;
        }

        .sidebar .soon {
            background: rgba(255, 255, 255, .08);
            color: #b8cfc2;
            border: 1px solid rgba(255, 255, 255, .08);
            font-size: 9px;
        }

        .sidebar .switch-area {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .13);
        }

        .sidebar .sidebar-footer {
            margin-top: 28px;
            padding: 20px 8px 0;
            border-top: 1px solid rgba(255, 255, 255, .13);
        }

        .sidebar .user-name {
            color: #ffffff;
            font-size: 14px;
        }

        .sidebar .user-role {
            color: #abc5b6;
            font-size: 11px;
        }

        .sidebar .logout-button {
            width: 100%;
            margin-top: 16px;
            padding: 11px 16px;
            background: transparent;
            border: 1px solid #547565;
            border-radius: 10px;
            color: #ffffff;
            font-size: 13px;
        }

        .sidebar .logout-button:hover {
            background: #245442;
            border-color: #789786;
        }

        .sidebar a:focus-visible,
        .sidebar button:focus-visible,
        .sidebar summary:focus-visible {
            outline: 3px solid #dce5b4;
            outline-offset: 2px;
        }

        @media (max-width: 800px) {
            .sidebar {
                padding: 20px;
                border-bottom: 0;
            }

            .sidebar .sidebar-brand {
                padding: 0 0 16px;
            }

            .sidebar .navigation summary {
                background: #245442;
                color: #ffffff;
            }

            .sidebar .sidebar-footer {
                margin-top: 16px;
            }
        }

        @media (max-width: 1100px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 800px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                padding: 20px;
                border-right: 0;
                border-bottom: 1px solid #dde7e1;
            }

            .sidebar-brand { padding: 0 0 16px; }

            .navigation summary {
                display: list-item;
                cursor: pointer;
                padding: 12px;
                border-radius: 8px;
                background: #edf4ef;
                font-weight: 600;
            }

            .nav-label { margin-top: 20px; }

            .sidebar-footer { margin-top: 16px; }

            .workspace { margin-left: 0; }

            .topbar { padding: 20px; }

            main { padding: 24px 20px; }

            .grid { grid-template-columns: 1fr; }

            .card { padding: 24px; }

            .topbar-context { display: none; }
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
                >
                    Diets<span>DNA</span>
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
                        @if (request()->routeIs($isAdminArea ? 'admin.dashboard' : 'dashboard'))
                            aria-current="page"
                        @endif
                    >
                        Dashboard
                    </a>

                    @if ($isAdminArea && auth()->user()->is_admin)
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                            @if (request()->routeIs('admin.users.*'))
                                aria-current="page"
                            @endif
                        >
                            Customers
                        </a>

                        <a
                            href="{{ route('admin.ingredients.index') }}"
                            class="nav-link {{ request()->routeIs('admin.ingredients.*') ? 'active' : '' }}"
                            @if (request()->routeIs('admin.ingredients.*'))
                                aria-current="page"
                            @endif
                        >
                            Ingredients
                        </a>

                        <a
                            href="{{ route('admin.recipes.index') }}"
                            class="nav-link {{ request()->routeIs('admin.recipes.*') ? 'active' : '' }}"
                            @if (request()->routeIs('admin.recipes.*'))
                                aria-current="page"
                            @endif
                        >
                            Recipes
                        </a>

                        <a
                            href="{{ route('admin.calculator-settings.edit') }}"
                            class="nav-link {{ request()->routeIs('admin.calculator-settings.*') ? 'active' : '' }}"
                            @if (request()->routeIs('admin.calculator-settings.*'))
                                aria-current="page"
                            @endif
                        >
                            Calculator settings
                        </a>
                    @endif

                    @if (! $isAdminArea)
                    <a
                        href="{{ route('nutrition-profile.edit') }}"
                        class="nav-link {{ request()->routeIs('nutrition-profile.*') ? 'active' : '' }}"
                        @if (request()->routeIs('nutrition-profile.*'))
                            aria-current="page"
                        @endif
                    >
                        Nutrition profile
                    </a>

                    <a
                        href="{{ route('food-preferences.edit') }}"
                        class="nav-link {{ request()->routeIs('food-preferences.*') ? 'active' : '' }}"
                        @if (request()->routeIs('food-preferences.*'))
                            aria-current="page"
                        @endif
                    >
                        Food preferences
                    </a>
                @endif

                    @foreach ($upcomingItems as $item)
                        <div class="nav-pending" aria-disabled="true">
                            <span>{{ $item }}</span>
                            <span class="soon">Soon</span>
                        </div>
                    @endforeach

                    @if (auth()->user()->is_admin)
                        <div class="switch-area">
                            <div class="nav-label">Switch workspace</div>

                            @if ($isAdminArea)
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
            <a href="{{ route('home') }}" class="brand">
                Diets<span>DNA</span>
            </a>
        </header>
    @endauth

    <main id="main-content" class="@guest guest-main @endguest">
        @if (session('status'))
            <div class="notice success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="notice errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
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