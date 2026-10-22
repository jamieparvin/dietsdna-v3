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

        .header {
            background: white;
            border-bottom: 1px solid #dde7e1;
        }

        .header-inner {
            max-width: 1100px;
            margin: auto;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            text-decoration: none;
            color: #17332c;
        }

        .brand span { color: #16805b; }

        main {
            max-width: 1100px;
            margin: auto;
            padding: 48px 24px;
        }

        .card {
            background: white;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            padding: 32px;
        }

        .auth-card {
            max-width: 460px;
            margin: 16px auto;
        }

        h1 { margin: 0 0 12px; }

        p {
            line-height: 1.6;
            color: #53675e;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: 600;
        }

        input:not([type="checkbox"]) {
            width: 100%;
            padding: 13px;
            border: 1px solid #b9cbc0;
            border-radius: 8px;
            font: inherit;
        }

        input:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #69b89a;
            outline-offset: 3px;
        }

        button {
            border: 0;
            border-radius: 8px;
            padding: 13px 20px;
            background: #167451;
            color: white;
            font: inherit;
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

        .links {
            text-align: center;
            margin-bottom: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 28px;
        }

        .grid h2 { font-size: 19px; }

        .badge {
            display: inline-block;
            background: #edf3ef;
            color: #53675e;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            .card { padding: 24px; }
            main { padding: 32px 16px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="{{ route('home') }}" class="brand">
                Diets<span>DNA</span>
            </a>

            @auth
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            @endauth
        </div>
    </header>

    <main>
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
</body>
</html>