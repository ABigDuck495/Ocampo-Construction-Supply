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
    <script>
    (function() {
        try {
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.classList.add('light-mode-pending');
            }
        } catch (e) {}
    })();
</script>
    @vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/settings.css'])
    </head>
    <body>
    @include('partials.sidebar')

    <main class="main">
        <div class="page-toolbar">
        <div class="header">
            <div>
                <h1>SYSTEM SETTINGS</h1>
                <p id="headerSub">Controls for inventory, logistics, and POS behavior</p>
            </div>
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

        <form id="settingsForm" method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            @php
                $groupTitles = [
                    'Inventory' => 'Inventory & Sourcing Controls',
                    'Logistics' => 'Logistics & Truck Capacity',
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