(() => {
    let theme = null;

    try {
        theme = localStorage.getItem('yawasla.theme');
    } catch {
    }

    if (theme !== 'light' && theme !== 'dark') {
        theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.setAttribute('data-bs-theme', theme);
})();
