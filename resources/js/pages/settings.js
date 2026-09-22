document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settingsForm');
    if (!form) return;

    const saveBtn = document.getElementById('settingsSaveBtn');
    const dirtyHint = document.getElementById('settingsDirtyHint');
    const flash = document.getElementById('settingsFlash');

    const initialState = new FormData(form);
    const initialEntries = [...initialState.entries()]
        .map(([key, value]) => `${key}=${value}`)
        .sort()
        .join('&');

    const setDirty = (isDirty) => {
        if (saveBtn) saveBtn.disabled = !isDirty;
        if (dirtyHint) {
            dirtyHint.textContent = isDirty ? 'Unsaved changes' : 'No unsaved changes';
            dirtyHint.classList.toggle('dirty', isDirty);
        }
    };

    const checkDirty = () => {
        const currentEntries = [...new FormData(form).entries()]
            .map(([key, value]) => `${key}=${value}`)
            .sort()
            .join('&');
        setDirty(currentEntries !== initialEntries);
    };

    // Start clean; the button only lights up once something changes.
    setDirty(false);

    form.addEventListener('input', checkDirty);
    form.addEventListener('change', checkDirty);

    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.4s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 4000);
    }
});