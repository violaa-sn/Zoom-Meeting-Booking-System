import { $, Swal } from '../utils/dom';
import { sessionEnded } from '../utils/http';
import { badge } from './badge';
import { confirmSubmit } from './alert';

/* ---------- Modal "Atur tanggal": batalkan satu tanggal dari booking berulang ---------- */
const STATE = { upcoming: ['Akan datang', 'blue'], done: ['Selesai', 'grey'], cancelled: ['Dibatalkan', 'red'] };

export const loadOccurrences = async (modal, trigger) => {
    const list = $('[data-occ-list]', modal);
    list.replaceChildren(Object.assign(document.createElement('li'), { className: 'list-group-item text-secondary', textContent: 'Memuat tanggal...' }));
    try {
        const res = await fetch(trigger.dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (sessionEnded(res)) return;
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();
        $('[data-occ-account]', modal).textContent = data.account;
        list.replaceChildren(...data.items.map((o) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
            const info = document.createElement('div');
            const b = Object.assign(document.createElement('b'), { textContent: o.date });
            const t = Object.assign(document.createElement('small'), { className: 'd-block text-secondary', textContent: o.time });
            info.append(b, t);
            const right = document.createElement('div');
            right.className = 'd-flex align-items-center gap-2 flex-shrink-0';
            right.append(badge(...STATE[o.state]));
            if (o.cancel_url) {
                const btn = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn-sm btn-outline-secondary', textContent: 'Batalkan' });
                btn.addEventListener('click', () => {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.action = o.cancel_url;
                    f.dataset.confirm = '';
                    f.dataset.confirmTitle = 'Batalkan tanggal ini?';
                    f.dataset.confirmText = `${o.date}, ${o.time}. Tanggal lain di seri ini tetap berjalan.`;
                    f.dataset.confirmButton = 'Ya, batalkan';
                    const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';
                    f.append(Object.assign(document.createElement('input'), { type: 'hidden', name: '_token', value: token }));
                    li.append(f);
                    if (Swal()) confirmSubmit(f); else f.submit();
                });
                right.append(btn);
            }
            li.append(info, right);
            return li;
        }));
    } catch {
        list.replaceChildren(Object.assign(document.createElement('li'), { className: 'list-group-item text-danger', textContent: 'Daftar tanggal belum bisa dimuat. Tutup lalu coba lagi.' }));
    }
};
