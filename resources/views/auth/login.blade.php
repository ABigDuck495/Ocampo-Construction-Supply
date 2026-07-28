<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ocampo Construction and Hardware Supplies</title>

    @vite('resources/css/app.css') {{-- adjust to your app's compiled Tailwind entry --}}

    <script>
        // Apply saved theme before paint, to avoid a flash of the wrong theme
        (function () {
            const saved = localStorage.getItem('ih-theme');
            const theme = saved || 'light';
            document.documentElement.classList.toggle('dark', theme === 'dark');
        })();
    </script>

    <style>
        :root {
            --ih-bg: #f3efe6;
            --ih-card: #ffffff;
            --ih-border: #111111;
            --ih-input-bg: #eae5da;
            --ih-input-border: #ded7c8;
            --ih-text: #16130f;
            --ih-subtext: #6b6459;
            --ih-label: #8a8375;
            --ih-icon-from: #f0a13a;
            --ih-icon-to: #d9791f;
            --ih-btn-from: #f0c98a;
            --ih-btn-to: #e6a95c;
            --ih-btn-text: #3d2a0f;
            --ih-btn-shadow: rgba(214, 158, 74, 0.35);
        }

        html.dark {
            --ih-bg: #0c0e12;
            --ih-card: #141824;
            --ih-border: #2a2f3d;
            --ih-input-bg: #1b202c;
            --ih-input-border: #2d3341;
            --ih-text: #f2f1ee;
            --ih-subtext: #9098a8;
            --ih-label: #7d8494;
            --ih-icon-from: #ffb545;
            --ih-icon-to: #f5a623;
            --ih-btn-from: #c8850f;
            --ih-btn-to: #9c6a0c;
            --ih-btn-text: #1a1305;
            --ih-btn-shadow: rgba(200, 133, 15, 0.4);
        }

        body {
            background: var(--ih-bg);
            color: var(--ih-text);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            transition: background-color .25s ease, color .25s ease;
        }

        .ih-top-rule {
            height: 3px;
            background: var(--ih-border);
        }

        .ih-mono {
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .ih-card {
            background: var(--ih-card);
            border: 1px solid var(--ih-border);
            transition: background-color .25s ease, border-color .25s ease;
        }

        .ih-icon {
            background: linear-gradient(160deg, var(--ih-icon-from), var(--ih-icon-to));
        }

        html.dark .ih-icon {
            box-shadow: 0 0 24px 2px rgba(245, 166, 35, 0.35);
        }

        .ih-input {
            background: var(--ih-input-bg);
            border: 1px solid var(--ih-input-border);
            color: var(--ih-text);
        }

        .ih-input::placeholder {
            color: var(--ih-subtext);
        }

        .ih-input:focus {
            outline: none;
            border-color: var(--ih-icon-to);
            box-shadow: 0 0 0 3px rgba(217, 121, 31, 0.15);
        }

        .ih-label {
            color: var(--ih-label);
            letter-spacing: .06em;
        }

        .ih-btn {
            background: linear-gradient(180deg, var(--ih-btn-from), var(--ih-btn-to));
            color: var(--ih-btn-text);
            box-shadow: 0 8px 20px -6px var(--ih-btn-shadow);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .ih-btn:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
        }

        .ih-btn:active {
            transform: translateY(0);
        }

        .ih-toggle {
            background: var(--ih-card);
            border: 1px solid var(--ih-border);
            color: var(--ih-text);
        }

        .ih-toggle:hover {
            filter: brightness(1.05);
        }

        .ih-subtitle {
            color: var(--ih-subtext);
        }
    </style>
</head>
<body class="min-h-screen relative">

    <div class="ih-top-rule w-full absolute top-0 left-0"></div>

    {{-- Theme toggle --}}
    <button
        type="button"
        id="theme-toggle"
        class="ih-toggle absolute top-6 right-6 h-10 w-10 rounded-full flex items-center justify-center shadow-sm"
        aria-label="Toggle light and dark mode"
    >
        {{-- Sun icon (shown in dark mode) --}}
        <svg id="icon-sun" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
        {{-- Moon icon (shown in light mode) --}}
        <svg id="icon-moon" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>

    <div class="flex flex-col items-center justify-center min-h-screen px-4 py-16">

        {{-- Brand --}}
        <div class="flex flex-col items-center mb-8">
            <div class="ih-icon h-16 w-16 rounded-2xl flex items-center justify-center mb-5">
                <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold tracking-wide uppercase text-center">Ocampo Construction and <br>Hardware Supplies</h1>
            <p class="ih-subtitle mt-2 text-sm text-center">Point of Sale &middot; Delivery &middot; Inventory Management</p>
        </div>

        {{-- Sign in card --}}
        <div class="ih-card w-full max-w-md rounded-2xl p-8 shadow-sm">
            <h2 class="text-xl font-bold uppercase tracking-wide mb-6">Sign In</h2>

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-400/50 bg-red-500/10 px-4 py-3 text-sm text-red-500">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="ih-label block text-xs font-semibold uppercase mb-2">Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center ih-subtitle">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter email"
                            autocomplete="email"
                            required
                            class="ih-input ih-mono w-full rounded-lg pl-10 pr-4 py-3 text-sm"
                        >
                    </div>
                </div>  
                    

                <div>
                    <label for="password" class="ih-label block text-xs font-semibold uppercase mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center ih-subtitle">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required
                            class="ih-input ih-mono w-full rounded-lg pl-10 pr-10 py-3 text-sm"
                        >
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute inset-y-0 right-3 flex items-center ih-subtitle"
                            aria-label="Show password"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="ih-btn w-full rounded-lg py-3 font-bold flex items-center justify-center gap-2">
                    Sign In
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M13 5l7 7-7 7"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <script>
        const html = document.documentElement;
        const sunIcon = document.getElementById('icon-sun');
        const moonIcon = document.getElementById('icon-moon');
        const toggleBtn = document.getElementById('theme-toggle');

        function syncIcons() {
            const isDark = html.classList.contains('dark');
            sunIcon.classList.toggle('hidden', !isDark);
            moonIcon.classList.toggle('hidden', isDark);
        }
        syncIcons();

        toggleBtn.addEventListener('click', () => {
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('ih-theme', isDark ? 'dark' : 'light');
            syncIcons();
        });

        // Show/hide password
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('toggle-password');
        togglePassword.addEventListener('click', () => {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            togglePassword.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>