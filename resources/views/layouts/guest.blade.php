<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&family=Playfair+Display:wght@700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="guest-body">
    <svg width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden;" aria-hidden="true">
        <defs>
            {{-- Ornamen mandala sudut kartu --}}
            <symbol id="card-mandala-corner" viewBox="0 0 110 110">
                <g fill="none" stroke="#8a7f70" stroke-width="1.15" opacity="0.45">
                    <path d="M0,0 L0,88 C18,86 32,78 42,66 C56,70 70,62 74,46 C86,36 92,18 92,0 Z" fill="#e6dfd5" fill-opacity="0.32" stroke="none" />
                    <circle cx="0" cy="0" r="24" />
                    <circle cx="0" cy="0" r="40" stroke-dasharray="3 2" />
                    <circle cx="0" cy="0" r="58" />
                    <circle cx="0" cy="0" r="76" />
                    <path d="M0,24 C10,24 16,32 12,42 C22,38 30,44 26,54 C36,50 44,54 42,64 C52,58 58,48 56,38 C66,40 72,30 64,22 C74,18 76,8 66,2" />
                    <path d="M0,40 C16,40 28,50 24,66 C38,60 50,66 48,80 C60,72 68,58 64,44 C78,46 84,32 76,18" />
                    <path d="M0,58 C20,58 36,70 36,90 M18,0 C18,18 30,30 48,30 M58,0 C58,20 70,36 90,36" />
                    <path d="M12,12 L68,68 M26,8 L56,38 M8,26 L38,56" stroke-opacity="0.5" />
                </g>
            </symbol>
        </defs>
    </svg>

    <div class="guest-shell">
        <header class="guest-school-header">
            <img class="guest-school-emblem" src="{{ asset('images/logo-smkim4.png') }}" alt="Logo SMK Istiqomah Muhammadiyah 4 Samarinda">
            <h1 class="guest-school-name">SMK ISTIQOMAH MUHAMMADIYAH 4</h1>
            <p class="guest-school-city">SMKIM4 SAMARINDA</p>
        </header>

        <div class="guest-stage">
            <main class="guest-card">
                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>
