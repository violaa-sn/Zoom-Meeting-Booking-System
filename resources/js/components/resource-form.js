import { $, $$ } from '../utils/dom';

/* ---------- Form tambah / ubah di offcanvas ---------- */
export const prepareResourceForm = (panel, btn) => {
    const form = $('form', panel);
    const edit = btn.dataset.mode === 'edit';
    const values = edit ? JSON.parse(btn.dataset.values || '{}') : {};
    form.reset();
    $$('.field-error', form).forEach((e) => (e.textContent = ''));
    $$('.is-invalid', form).forEach((e) => e.classList.remove('is-invalid'));
    form.action = edit ? btn.dataset.action : form.dataset.createAction;
    const method = $('[data-method-input]', form);
    if (method) method.disabled = !edit;
    if (form.elements._edit_id) form.elements._edit_id.value = edit ? (values.id ?? '') : '';
    Object.entries(values).forEach(([key, val]) => {
        const f = form.elements[key];
        if (!f) return;
        if (f instanceof RadioNodeList) {
            const box = [...f].find((x) => x.type === 'checkbox');
            if (box) box.checked = !!val; else f.value = val ?? '';
        } else f.value = val ?? '';
    });
    $$('[data-create-required]', form).forEach((i) => (i.required = !edit));
    $('[data-overlay-title]', panel).textContent = edit ? panel.dataset.titleEdit : panel.dataset.titleCreate;
    $('[data-submit-label]', panel).textContent = edit ? 'Simpan perubahan' : panel.dataset.submitCreate;
};
