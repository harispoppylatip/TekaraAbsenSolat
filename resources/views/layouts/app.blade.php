@php
    $menuGroups = \App\Support\Menu::groups(auth()->user());
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ringkasan') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/logo-smkim4.webp') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <p class="sidebar-brand-name">Absen Sholat</p>
                <p class="sidebar-brand-note">SMK Istiqomah Muhammadiyah 4</p>
            </div>

            <nav class="sidebar-nav" aria-label="Menu utama">
                @foreach ($menuGroups as $group)
                    <p class="sidebar-group-label">{{ $group['label'] }}</p>

                    @foreach ($group['items'] as $item)
                        <a class="nav-link @if (request()->routeIs($item['active'])) is-active @endif"
                            href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                    @endforeach
                @endforeach
            </nav>

            <div class="sidebar-foot">
                <p class="sidebar-account">
                    <strong>{{ auth()->user()->name }}</strong>
                    {{ auth()->user()->roleLabel() }}@if (auth()->user()->class_name)
                        · {{ auth()->user()->class_name }}
                    @endif
                </p>

                <a class="nav-link @if (request()->routeIs('password.edit')) is-active @endif" href="{{ route('password.edit') }}">Pengaturan Akun</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-link nav-link-button" type="submit">Keluar</button>
                </form>
            </div>
        </aside>

        <div class="sidebar-backdrop" data-sidebar-backdrop></div>

        <div class="app-main">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="sidebar">Menu</button>

                <div>
                    <p class="topbar-heading">@yield('title', 'Ringkasan')</p>
                    @hasSection('note')
                        <p class="topbar-note">@yield('note')</p>
                    @endif
                </div>

                <p class="topbar-user">
                    <strong>{{ auth()->user()->name }}</strong>
                    {{ auth()->user()->roleLabel() }}
                </p>
            </header>

            <main class="app-content">
                @include('partials.flash')

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>
