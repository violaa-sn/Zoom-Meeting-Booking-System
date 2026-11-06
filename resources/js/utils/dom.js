/** Helper pemilih elemen DOM. */
export const $ = (s, r = document) => r.querySelector(s);
export const $$ = (s, r = document) => [...r.querySelectorAll(s)];

/** window.Swal dan window.bootstrap diisi di app.js setelah modul ini dimuat, jadi dibaca saat dipakai. */
export const Swal = () => window.Swal;
export const BS = () => window.bootstrap;
