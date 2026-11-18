import { Toast } from 'bootstrap';
import '@phosphor-icons/web/regular';

for (const button of document.querySelectorAll('[data-theme-toggle]')) {
    button.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-bs-theme', next);

        try {
            localStorage.setItem('yawasla.theme', next);
        } catch {
        }
    });
}

for (const toast of document.querySelectorAll<HTMLElement>('[data-flash-autohide]')) {
    Toast.getOrCreateInstance(toast, { delay: 5000 }).show();
}
