import '../scss/_admin.scss';
import { TYPING_PAUSE } from '@shader-ui/core';

const watchCheck = (field: HTMLInputElement) => {
    const url = new URL(field.dataset.check!, window.location.href);
    const errorId = `${field.id}-error`;
    let timer = 0;
    let controller: AbortController | null = null;
    let checked = field.value.trim();

    const show = (taken: boolean) => {
        let error = document.getElementById(errorId);

        if (taken && !error) {
            error = document.createElement('div');
            error.className = 'invalid-feedback';
            error.id = errorId;
            field.insertAdjacentElement('afterend', error);
        }

        if (taken && error) {
            error.textContent = field.dataset.checkMessage ?? '';
            field.setAttribute('aria-describedby', errorId);
        }

        field.classList.toggle('is-invalid', taken);
        field.setAttribute('aria-invalid', String(taken));
    };

    const run = async () => {
        window.clearTimeout(timer);
        const name = field.value.trim();

        controller?.abort();

        if (name === checked) {
            field.removeAttribute('aria-busy');
            return;
        }

        const current = new AbortController();
        controller = current;
        url.searchParams.set('name', name);

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: current.signal });
            const result: unknown = await response.json();

            if (typeof result === 'object' && result !== null && 'taken' in result) {
                checked = name;
                show(result.taken === true);
            }
        } catch {
        } finally {
            if (!current.signal.aborted) {
                field.removeAttribute('aria-busy');
            }
        }
    };

    field.addEventListener('input', () => {
        window.clearTimeout(timer);

        if (field.value.trim() === '') {
            controller?.abort();
            field.removeAttribute('aria-busy');
            return;
        }

        field.setAttribute('aria-busy', 'true');
        timer = window.setTimeout(run, TYPING_PAUSE);
    });

    field.addEventListener('change', run);
};

document.querySelectorAll<HTMLInputElement>('input[data-check]').forEach(watchCheck);
