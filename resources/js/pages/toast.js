/* ============================================================
   TOAST - lightweight notification system (replaces alert())
   ============================================================ */

let container = null;

function ensureContainer() {
    if (container) return container;
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// type: 'info' | 'success' | 'error'
export function toast(message, type = 'info', duration = 3500) {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;

    const wrap = ensureContainer();
    wrap.appendChild(el);

    requestAnimationFrame(() => el.classList.add('show'));

    setTimeout(() => {
        el.classList.remove('show');
        el.addEventListener('transitionend', () => el.remove(), { once: true });
    }, duration);
}