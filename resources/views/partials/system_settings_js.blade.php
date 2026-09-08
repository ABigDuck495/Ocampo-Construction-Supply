<script>
    window.SYSTEM_SETTINGS = @json($systemSettings ?? []);
    if (typeof window.SYSTEM_SETTINGS.isFeatureEnabled !== 'function') {
        window.SYSTEM_SETTINGS.isFeatureEnabled = function(key) {
            try {
                const v = window.SYSTEM_SETTINGS[key];
                if (typeof v === 'string') return v === 'true' || v === '1' || v === 'on';
                return Boolean(v);
            } catch (e) { return false; }
        };
    }
    // Shim `opr.featuresPrivate.isFeatureEnabled` which some bundled code expects
    try {
        if (typeof window.opr !== 'object') window.opr = {};
        if (typeof window.opr.featuresPrivate !== 'object') window.opr.featuresPrivate = {};
        if (typeof window.opr.featuresPrivate.isFeatureEnabled !== 'function') {
            window.opr.featuresPrivate.isFeatureEnabled = function(key) {
                try {
                    // prefer SYSTEM_SETTINGS mapping if present
                    if (window.SYSTEM_SETTINGS && typeof window.SYSTEM_SETTINGS.isFeatureEnabled === 'function') {
                        return window.SYSTEM_SETTINGS.isFeatureEnabled(key);
                    }
                    return false;
                } catch (e) { return false; }
            };
        }
    } catch (e) {
        // no-op
    }
</script>
