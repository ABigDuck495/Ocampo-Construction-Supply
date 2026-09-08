<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings - Ocampo Construction and Hardware Supplies</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- <script>
        window.SYSTEM_SETTINGS = @json($systemSettings ?? []);
        // ensure callers expecting a helper method don't crash
        if (typeof window.SYSTEM_SETTINGS.isFeatureEnabled !== 'function') {
            window.SYSTEM_SETTINGS.isFeatureEnabled = function(key) {
                try {
                    const v = window.SYSTEM_SETTINGS[key];
                    if (typeof v === 'string') return v === 'true' || v === '1' || v === 'on';
                    return Boolean(v);
                } catch (e) { return false; }
            };
        }
    </script> -->
    @vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/settings.css'])
    </head>
    <body>

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <div>
                <div class="brand-name">Ocampo Construction and Hardware Supplies</div>
                <div class="brand-sub">POS</div>
            </div>
        </div>
        <nav class="nav">
            <a href="{{ route('pos.index') }}" class="nav-item"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
                POS</span></a>
            <a href="{{ route('deliveries.index') }}" class="nav-item"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
                DELIVERY</span><span class="nav-badge" id="sidebarBadge">5</span></a>
            <a href="{{ route('inventory.index') }}" class="nav-item"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M5 7v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                INVENTORY</span></a>
            <a href="{{ route('reports.index') }}" class="nav-item"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                REPORTS</span></a>
            <a href="{{ route('users.index') }}" class="nav-item"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15 20a5 5 0 0 1 8 0"/></svg>
                USERS</span></a>
            <a href="{{ route('settings.index') }}" class="nav-item active"><span class="lbl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                SETTINGS</span></a>
        </nav>

        <div class="sidebar-footer">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle light/dark mode">
                <span class="theme-toggle-icon" id="themeIcon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </span>
                <span class="theme-toggle-label" id="themeLabel">DARK MODE</span>
            </button>

            <a href="{{ url('/login') }}" class="signout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                SIGN OUT
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="header">
            <div>
                <h1>SYSTEM SETTINGS</h1>
                <p id="headerSub">Controls for inventory, logistics, and POS behavior</p>
            </div>
        </div>

        @if (session('status'))
            <div class="settings-flash" id="settingsFlash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="settings-flash error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (collect($groupedSettings)->isEmpty())
            <div class="settings-card" style="padding:18px;">
                <div style="font-weight:800;margin-bottom:6px;">No system settings found</div>
                <div style="color:var(--text-dim);">There are no settings records in the database. Run the SystemSettingsSeeder or create entries in the `system_settings` table to populate this page.</div>
            </div>
        @endif

        <form id="settingsForm" method="POST" action="{{ url('/settings') }}">
            @csrf
            @method('PUT')

            @php
                $groupTitles = [
                    'INVENTORY' => 'Inventory & Sourcing Controls',
                    'LOGISTICS' => 'Logistics & Truck Capacity',
                    'POS'       => 'POS & Transaction Behaviors',
                ];
            @endphp

            @foreach ($groupedSettings as $groupKey => $settings)
                <section class="settings-group">
                    <div class="settings-group-head">
                        <h2>{{ $groupTitles[$groupKey] ?? ucfirst(strtolower($groupKey)) }}</h2>
                    </div>

                    <div class="settings-card">
                        @foreach ($settings as $setting)
                            <div class="setting-row" data-key="{{ $setting->setting_key }}">
                                <div class="setting-info">
                                    <label class="setting-label" for="setting-{{ $setting->setting_key }}">
                                        {{ str_replace('_', ' ', $setting->setting_key) }}
                                    </label>
                                    @if ($setting->description)
                                        <p class="setting-desc">{{ $setting->description }}</p>
                                    @endif
                                </div>

                                <div class="setting-control">
                                    @if ($setting->is_boolean)
                                        <label class="toggle">
                                            <input
                                                type="checkbox"
                                                id="setting-{{ $setting->setting_key }}"
                                                name="settings[{{ $setting->setting_key }}]"
                                                value="1"
                                                @checked($setting->bool_value)
                                            >
                                            <span class="toggle-slider"></span>
                                        </label>
                                    @elseif ($setting->is_numeric)
                                        <input
                                            type="number"
                                            step="0.01"
                                            id="setting-{{ $setting->setting_key }}"
                                            name="settings[{{ $setting->setting_key }}]"
                                            value="{{ $setting->setting_value }}"
                                            class="setting-input setting-input-number"
                                        >
                                    @else
                                        <input
                                            type="text"
                                            id="setting-{{ $setting->setting_key }}"
                                            name="settings[{{ $setting->setting_key }}]"
                                            value="{{ $setting->setting_value }}"
                                            class="setting-input"
                                        >
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="settings-savebar">
                <span class="settings-savebar-hint" id="settingsDirtyHint">No unsaved changes</span>
                <button type="submit" class="btn-submit" id="settingsSaveBtn">SAVE CHANGES</button>
            </div>
        </form>
    </main>

    @vite(['resources/js/pages/settings.js', 'resources/js/pages/sidebar.js'])
    </body>
    </html>