/* Tombol mata: ganti tipe input password <-> text. */
export const initPasswordToggle = () => {
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-password-toggle]");
    if (!btn) return;
    const input = btn.closest(".password-field")?.querySelector("input");
    if (!input) return;
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    btn.setAttribute("aria-pressed", String(show));
    btn.setAttribute(
      "aria-label",
      show ? "Sembunyikan password" : "Tampilkan password",
    );
    btn.querySelector("[data-eye-on]").hidden = show;
    btn.querySelector("[data-eye-off]").hidden = !show;
  });

  // Form di-reset (mis. offcanvas tambah/ubah dibuka lagi): kembalikan ke tersembunyi.
  document.addEventListener("reset", (e) => {
    e.target.querySelectorAll?.("[data-password-toggle]").forEach((btn) => {
      const input = btn.closest(".password-field")?.querySelector("input");
      if (input) input.type = "password";
      btn.setAttribute("aria-pressed", "false");
      btn.setAttribute("aria-label", "Tampilkan password");
      btn.querySelector("[data-eye-on]").hidden = false;
      btn.querySelector("[data-eye-off]").hidden = true;
    });
  });
};
