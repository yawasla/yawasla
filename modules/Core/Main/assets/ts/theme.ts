(() => {
    let theme = null;

    try {
        theme = localStorage.getItem('yawasla.theme');
    } catch {
    }

    if (theme !== 'light' && theme !== 'dark') {
        theme = 'dark';
    }

    document.documentElement.setAttribute('data-bs-theme', theme);
})();
