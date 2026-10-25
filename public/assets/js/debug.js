const panel = document.getElementById('debug-panels');
const handle = panel?.querySelector('.debug-resize');
const key = 'yawasla.debug.height';

const resize = (ratio) => {
    panel.style.setProperty('--bs-offcanvas-height', `${ratio * 100}dvh`);
};

const load = () => {
    try {
        const saved = Number(sessionStorage.getItem(key));
        return saved >= 0.1 && saved <= 1 ? saved : null;
    } catch {
        return null;
    }
};

const save = (ratio) => {
    try {
        sessionStorage.setItem(key, String(ratio));
    } catch {
    }
};

const restore = () => {
    const saved = load();
    saved ? resize(saved) : panel.style.removeProperty('--bs-offcanvas-height');
};

if (handle) {
    restore();

    handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
    });

    handle.addEventListener('pointermove', (event) => {
        if (handle.hasPointerCapture(event.pointerId)) {
            resize(Math.min(Math.max(1 - event.clientY / window.innerHeight, 0), 1));
        }
    });

    handle.addEventListener('lostpointercapture', () => {
        const ratio = panel.getBoundingClientRect().height / window.innerHeight;

        if (ratio < 0.1) {
            panel.addEventListener('hidden.bs.offcanvas', restore, { once: true });
            bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
            return;
        }

        const final = ratio > 0.9 ? 1 : ratio;
        resize(final);
        save(final);
    });
}

for (const list of document.querySelectorAll('.debug-tabs, .debug-stats')) {
    list.addEventListener('wheel', (event) => {
        if (list.scrollWidth > list.clientWidth && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
            event.preventDefault();
            list.scrollLeft += event.deltaY;
        }
    }, { passive: false });
}
