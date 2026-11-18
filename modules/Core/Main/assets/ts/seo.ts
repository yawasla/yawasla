type Field = HTMLInputElement | HTMLTextAreaElement;

document.querySelectorAll<Field>('[data-counter]').forEach((field) => {
    const output = document.getElementById(`${field.id}-count`);

    if (!output) {
        return;
    }

    const update = () => {
        output.textContent = `${field.value.length} / ${field.dataset.counter}`;
    };

    field.addEventListener('input', update);
    update();
});

document.querySelectorAll<HTMLElement>('[data-preview]').forEach((preview) => {
    const field = document.getElementById(preview.dataset.preview ?? '') as Field | null;

    if (!field) {
        return;
    }

    const empty = preview.textContent ?? '';
    const update = () => {
        preview.textContent = field.value.trim() || empty;
    };

    field.addEventListener('input', update);
    update();
});
