import 'bootstrap';

for (const button of document.querySelectorAll('[data-theme-toggle]')) {
    button.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

        try {
            localStorage.setItem('yawasla.theme', next);
        } catch {
            document.documentElement.setAttribute('data-bs-theme', next);
            return;
        }

        location.reload();
    });
}
