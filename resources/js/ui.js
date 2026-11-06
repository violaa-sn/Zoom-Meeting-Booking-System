/*
 * Perilaku UI Zoom Booking (Bootstrap 5 + SweetAlert2).
 * Offcanvas/modal pke komponen Bootstrap; file ini merangkai modul di
 * components/, pages/, dan utils/: form tambah/ubah, konfirmasi SweetAlert,
 * form booking 2 langkah, dan jam operasional.
 */
import { $, $$, BS } from "./utils/dom";
import { toast, dialog, initConfirm } from "./components/alert";
import { initPasswordToggle } from "./components/password-toggle";
import { prepareResourceForm } from "./components/resource-form";
import { loadOccurrences } from "./components/occurrences-modal";
import { loadApproval } from "./components/approval-modal";
import {
  form,
  q,
  setStep,
  clearErrors,
  syncRecurrence,
  syncDuration,
  addMinutes,
  initBookingForm,
} from "./pages/booking-form";

initConfirm();
initPasswordToggle();
initBookingForm();

/* ---------- Event Bootstrap ---------- */
document.addEventListener("show.bs.offcanvas", (e) => {
  const panel = e.target,
    trigger = e.relatedTarget;
  if (panel.id === "booking-panel" && form) {
    if (!trigger) return; // dibuka otomatis setelah error validasi: pertahankan isian dan pesan error
    clearErrors();
    setStep(1);
    if (trigger.hasAttribute("data-slot")) {
      // klik kotak kalender: isi tanggal dan jam
      q("starts_on").value = trigger.dataset.date;
      q("start_time").value = trigger.dataset.start;
      q("end_time").value = addMinutes(trigger.dataset.start, 60);
      syncRecurrence();
      syncDuration();
    }
  } else if (panel.hasAttribute("data-resource") && trigger?.dataset.mode) {
    prepareResourceForm(panel, trigger);
  }
});
document.addEventListener("shown.bs.offcanvas", (e) => {
  $(
    "input:not([type=hidden]):not([disabled]),select:not([disabled])",
    e.target,
  )?.focus({ preventScroll: true });
});
document.addEventListener("show.bs.modal", (e) => {
  const modal = e.target,
    trigger = e.relatedTarget;
  if (modal.id === "occurrences-modal" && trigger)
    loadOccurrences(modal, trigger);
  if (modal.id === "approve-modal" && trigger) loadApproval(modal, trigger);
  if (modal.id === "reject-modal" && trigger) {
    $("form", modal).action = trigger.dataset.action;
    $("[data-reject-summary]", modal).textContent =
      trigger.dataset.summary || "";
    $("textarea", modal).value = "";
  }
});

document.addEventListener("shown.bs.modal", (e) =>
  $("textarea,input:not([type=hidden])", e.target)?.focus(),
);

/* ---------- Saat halaman dimuat ---------- */
document.addEventListener("DOMContentLoaded", () => {
  $$("[data-flash]").forEach((el) => {
    const type = el.dataset.flash;
    if (type === "success") toast("success", el.dataset.message);
    else
      dialog(
        type,
        el.dataset.message,
        type === "error" ? "Tidak berhasil" : undefined,
      );
  });
  $$("[data-open-on-load]").forEach((el) => {
    const C = el.classList.contains("offcanvas") ? BS().Offcanvas : BS().Modal;
    C.getOrCreateInstance(el).show();
  });
  $(".app-tabs .nav-link.active")?.scrollIntoView({
    inline: "center",
    block: "nearest",
  });
  $$("[data-autosubmit]").forEach((el) =>
    el.addEventListener("change", () => el.form.submit()),
  );
});

window.ZB = { toast, dialog };
