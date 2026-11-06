import { $ } from "../utils/dom";
import { sessionEnded } from "../utils/http";
import { badge } from "./badge";

/* ---------- Modal "Setujui": admin memilih akun Zoom dari rekomendasi ---------- */

const message = (text, cls = "text-secondary") =>
  Object.assign(document.createElement("p"), {
    className: `${cls} mb-0`,
    textContent: text,
  });

export const loadApproval = async (modal, trigger) => {
  const form = $("form", modal);
  const list = $("[data-approve-accounts]", modal);
  const none = $("[data-approve-none]", modal);
  const submit = $("[data-approve-submit]", modal);

  form.action = trigger.dataset.action;
  $("[data-approve-summary]", modal).textContent =
    trigger.dataset.summary || "";
  none.hidden = true;
  submit.disabled = true;
  list.replaceChildren(message("Memuat akun yang tersedia..."));

  try {
    const res = await fetch(trigger.dataset.url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });
    if (sessionEnded(res)) return;
    if (!res.ok) throw new Error(res.status);
    const data = await res.json();

    list.replaceChildren();
    let chosen = false;
    data.accounts.forEach((a) => {
      const label = document.createElement("label");
      label.className =
        "choice choice--pick" + (a.available ? "" : " is-disabled");
      const input = Object.assign(document.createElement("input"), {
        type: "radio",
        name: "zoom_account_id",
        value: a.id,
        disabled: !a.available,
        className: "form-check-input",
      });
      // Akun pertama yang tersedia (akun utama paling atas) dipilih otomatis, admin bebas menggantinya
      if (a.available && !chosen) {
        input.checked = true;
        chosen = true;
      }
      const body = document.createElement("div");
      const title = document.createElement("div");
      title.className = "choice__title";
      const dot = document.createElement("i");
      dot.style.setProperty("--c", a.color || "#3b6ee0");
      const name = document.createElement("span");
      name.textContent = a.name;
      title.append(dot, name);
      if (a.is_default) title.append(badge("Akun utama", "blue"));
      title.append(badge(a.type, "grey"));
      const meta = document.createElement("small");
      meta.textContent = `Kapasitas ${a.capacity} meeting, hingga ${a.participants} peserta`;
      const status = document.createElement("small");
      status.className = a.available ? "ok" : "reason";
      status.textContent = a.available
        ? "Kosong untuk semua tanggal"
        : a.reason;
      body.append(title, meta, status);
      label.append(input, body);
      list.append(label);
    });

    if (data.accounts.length === 0)
      list.append(message(`Belum ada akun ${data.type} yang aktif.`));
    none.hidden = chosen;
    submit.disabled = !chosen;
  } catch {
    list.replaceChildren(
      message(
        "Daftar akun belum bisa dimuat. Tutup lalu coba lagi.",
        "text-danger",
      ),
    );
  }
};
