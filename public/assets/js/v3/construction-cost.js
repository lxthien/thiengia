const form = document.querySelector('[data-cost-form]');
if (form) {
    const result = document.querySelector('[data-cost-result]');
    const stale = document.querySelector('[data-cost-stale]');
    const typeNote = document.querySelector('[data-cost-type-note]');
    form.addEventListener('input', () => {
        if (stale) stale.hidden = false;
    });
    form.addEventListener('change', () => {
        if (stale) stale.hidden = false;
        typeNote.hidden = form.elements['form[type]'].value !== '3';
    });
    form.querySelectorAll('[data-cost-dimension]').forEach(input => {
        const validate = () => {
            const value = input.value.trim();
            input.setCustomValidity(value && (!/^\d+(\.\d+)?$/.test(value) || !Number.isFinite(Number(value)) || Number(value) <= 0)
                ? 'Vui lòng nhập số lớn hơn 0, ví dụ 4.5.' : '');
        };
        input.addEventListener('input', validate);
        validate();
    });
    form.addEventListener('reset', event => {
        event.preventDefault();
        window.location.assign(form.dataset.resetUrl);
    });
    if (result?.dataset.costResult === 'ready') {
        result.focus({ preventScroll: true });
        result.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
}
