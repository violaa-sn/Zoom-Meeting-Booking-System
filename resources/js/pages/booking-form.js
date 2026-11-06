import { $, $$ } from '../utils/dom';
import { sessionEnded } from '../utils/http';
import { badge } from '../components/badge';
import { dialog } from '../components/alert';

/* ---------- Booking: form 2 langkah ---------- */
export const form = $('#booking-form');
export const q = (n) => form?.elements[n];
const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const OPEN = 6 * 60, CLOSE = 24 * 60;

/** Menit selesai: 00:00 dianggap tengah malam (24:00). */
const endMinute = (end) => (end === '00:00' ? CLOSE : toMin(end));

/** Jam operasional 06:00 sampai 24:00. Menit bebas (mis. 09:12). */
const outOfHours = (start, end) => {
    if (!start || !end) return false;
    return toMin(start) < OPEN || endMinute(end) > CLOSE;
};

export const setStep = (n) => {
    form.dataset.step = n;
    $$('[data-step-show]', form).forEach((el) => (el.hidden = +el.dataset.stepShow !== n));
    $$('[data-step-ind]', form).forEach((el) => el.classList.toggle('is-active', +el.dataset.stepInd === n));
    $('#booking-panel [data-overlay-title]').textContent = n === 1 ? 'Ajukan booking Zoom' : 'Akun yang tersedia';
};
export const clearErrors = () => {
    $$('.field-error', form).forEach((e) => (e.textContent = ''));
    $$('.is-invalid', form).forEach((e) => e.classList.remove('is-invalid'));
    $('[data-form-error]', form).hidden = true;
};

// Error dari server memakai nama field internal; tampilkan di field yang dilihat user
const ERROR_ALIAS = { recurrence_frequency: 'recurrence_preset' };

const showErrors = (errors) => {
    if (errors.operating_hours) { dialog('warning', errors.operating_hours[0]); return; }
    let first = null;
    Object.entries(errors).forEach(([key, msgs]) => {
        const raw = key.split('.')[0];
        const base = ERROR_ALIAS[raw] || raw;
        const slot = $(`[data-error-for="${base}"]`, form);
        if (slot) slot.textContent = msgs[0];
        const input = q(base) || q(`${base}[]`);
        if (input?.classList) input.classList.add('is-invalid');
        first ??= input;
        if (!slot) { const box = $('[data-form-error]', form); box.hidden = false; $('[data-form-error-text]', box).textContent = msgs[0]; }
    });
    first?.focus?.();
};

/** "Durasi: 1 jam 12 menit" di bawah jam mulai/selesai, supaya user yakin dengan menit yang dipilih. */
const formatDuration = (minutes) => {
    const h = Math.floor(minutes / 60), m = minutes % 60;
    return [h ? `${h} jam` : '', m ? `${m} menit` : ''].filter(Boolean).join(' ');
};
export const syncDuration = () => {
    const el = $('[data-duration]', form);
    const start = q('start_time').value, end = q('end_time').value;
    if (!start || !end) { el.hidden = true; return; }
    const diff = endMinute(end) - toMin(start);
    el.hidden = false;
    el.classList.toggle('is-error', diff <= 0);
    el.textContent = diff <= 0 ? 'Jam selesai harus setelah jam mulai.' : `Durasi: ${formatDuration(diff)}`;
};

/** Teks pilihan "Selama minggu pada hari Jumat" / "Selama bulan pada tanggal 6" mengikuti tanggal yang dipilih. */
const updatePresetLabels = () => {
    const select = q('recurrence_preset');
    const date = q('starts_on').value;
    const set = (value, text) => {
        const option = [...select.options].find((o) => o.value === value);
        if (option) option.textContent = text;
    };
    set('week', date ? `Selama minggu pada hari ${DAY_NAMES[new Date(`${date}T00:00:00`).getDay()]}` : 'Selama minggu');
    set('month', date ? `Selama bulan pada tanggal ${Number(date.slice(8, 10))}` : 'Selama bulan');
};

export const syncRecurrence = () => {
    const preset = q('recurrence_preset').value;
    const custom = preset === 'custom';
    const recurring = preset !== 'none';
    const unit = custom ? q('recurrence_unit').value : preset;

    $$('[data-custom]', form).forEach((e) => (e.hidden = !custom));

    // Tampilkan picker hari untuk SEMUA satuan (hari, minggu, bulan, tahun)
    // Sebelumnya hanya 'week'; sekarang semua satuan mendukung kustom hari
    $$('[data-days-picker]', form).forEach((e) => (e.hidden = !custom));

    $$('[data-recur]', form).forEach((e) => (e.hidden = !recurring));

    const type = $('[name=recurrence_end_type]:checked', form)?.value;
    q('recurrence_ends_on').disabled = type !== 'on';
    q('recurrence_ends_after').disabled = type !== 'after';

    // Kustom tanpa hari terpilih: pakai hari dari tanggal yang dipilih sebagai default
    if (custom && !$('[name="days[]"]:checked', form) && q('starts_on').value) {
        const dow = new Date(q('starts_on').value + 'T00:00:00').getDay();
        const box = $(`[name="days[]"][value="${dow}"]`, form);
        if (box) box.checked = true;
    }
    updatePresetLabels();
};

/** Hanya untuk dilihat: user tidak memilih akun, admin yang memilih saat menyetujui. */
const renderAccounts = (data) => {
    const list = $('[data-accounts]', form);
    list.replaceChildren();
    $('[data-summary]', form).textContent = data.summary;
    let anyAvailable = false;
    data.accounts.forEach((a) => {
        anyAvailable ||= a.available;
        const card = document.createElement('div');
        card.className = 'choice choice--info' + (a.available ? '' : ' is-disabled');
        const body = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'choice__title';
        const dot = document.createElement('i');
        dot.style.setProperty('--c', a.color || '#3b6ee0');
        const name = document.createElement('span');
        name.textContent = a.name;
        title.append(dot, name);
        if (a.is_default) title.append(badge('Akun utama', 'blue'));
        title.append(badge(a.type, 'grey'));
        const meta = document.createElement('small');
        meta.textContent = `Kapasitas ${a.capacity} meeting, hingga ${a.participants} peserta`;
        const status = document.createElement('small');
        status.className = a.available ? 'ok' : 'reason';
        status.textContent = a.available ? 'Tersedia untuk semua tanggal' : a.reason;
        body.append(title, meta, status);
        card.append(body);
        list.append(card);
    });
    $('[data-no-account]', form).hidden = anyAvailable;
    // Pengajuan hanya bisa dikirim kalau minimal satu akun masih kosong
    $('[data-submit]', form).disabled = !anyAvailable;
};

const recommend = async (btn) => {
    clearErrors();
    const start = q('start_time').value, end = q('end_time').value;
    // Dicek di browser dulu; server tetap memeriksa ulang.
    if (start && end && endMinute(end) <= toMin(start) && !outOfHours(start, end)) {
        dialog('warning', 'Jam selesai harus setelah jam mulai.');
        return;
    }
    if (outOfHours(start, end)) {
        dialog('warning', form.dataset.outOfHoursMessage || 'Hanya bisa 6 pagi - 12 malam');
        return;
    }
    const params = new URLSearchParams();
    for (const [k, v] of new FormData(form)) {
        if (['_token', '_method', '_form', '_edit_id'].includes(k)) continue;
        params.append(k, v);
    }
    const preset = q('recurrence_preset').value;
    if (preset === 'none') ['recurrence_end_type', 'recurrence_ends_on', 'recurrence_ends_after'].forEach((k) => params.delete(k));
    if (preset !== 'custom') ['repeat_interval', 'recurrence_unit', 'days[]'].forEach((k) => params.delete(k));
    // Catatan: days[] sekarang dikirim untuk semua satuan (hari, minggu, bulan, tahun)
    // Tidak perlu menghapus days[] berdasarkan unit lagi

    btn.disabled = true;
    try {
        const res = await fetch(`${form.dataset.recommendUrl}?${params}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
        });
        if (sessionEnded(res)) return;
        if (res.status === 422) { showErrors((await res.json()).errors); return; }
        if (!res.ok) throw new Error(res.status);
        renderAccounts(await res.json());
        setStep(2);
    } catch {
        dialog('error', 'Daftar akun belum bisa dimuat. Periksa koneksi lalu coba lagi.', 'Tidak berhasil');
    } finally {
        btn.disabled = false;
    }
};

/** Tambah menit, tapi tidak melewati tengah malam (23:30 + 60 menit = 00:00, bukan 00:30 besok). */
export const addMinutes = (t, add) => {
    const total = Math.min(toMin(t) + add, 1440) % 1440;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

export const initBookingForm = () => {
    if (!form) return;
    form.addEventListener('change', (e) => {
        if (['recurrence_preset', 'recurrence_unit', 'recurrence_end_type', 'starts_on'].includes(e.target.name)) syncRecurrence();
        if (e.target.name === 'purpose') $('[data-purpose-other]', form).hidden = e.target.value !== 'lainnya';
    });
    form.addEventListener('input', (e) => {
        if (['start_time', 'end_time'].includes(e.target.name)) syncDuration();
    });
    form.addEventListener('click', (e) => {
        const next = e.target.closest('[data-next]');
        if (next) recommend(next);
        if (e.target.closest('[data-back]')) setStep(1);
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && form.dataset.step !== '2') { e.preventDefault(); $('[data-next]', form).click(); }
    });
    syncRecurrence();
    syncDuration();
    $('[data-purpose-other]', form).hidden = q('purpose').value !== 'lainnya';
    setStep(1);
};