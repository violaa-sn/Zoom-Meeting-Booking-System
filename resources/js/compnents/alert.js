import { Swal } from "../utils/dom";

/* ---------- SweetAlert ---------- */
export const toast = (icon, title) =>
    Swal()?.fire({
        toast: true,
        position: "top-end",
        icon,
        title,
        showConfirmButton: false,
        timer: 3800,
        timerProgressBar: true,
    });

export const dialog = (icon, text, title) =>
    Swal()?.fire({
        icon,
        title,
        text,
        confirmButtonText: "Mengerti",
        confirmButtonColor: "#3b6ee0",
    });

export const confirmSubmit = async (form) => {
    const r = await Swal().fire({
        icon: form.dataset.confirmIcon || "warning",
        title: form.dataset.confirmTitle || "Yakin?",
        text: form.dataset.confirmText || "",
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || "Ya, lanjutkan",
        cancelButtonText: "Batal",
        confirmButtonColor: form.dataset.confirmColor || "#c9372c",
        reverseButtons: true,
        focusCancel: true,
    });
    if (r.isConfirmed) form.submit();
};

export const initConfirm = () => {
    document.addEventListener("submit", (e) => {
        if (e.target.matches("[data-confirm]") && Swal()) {
            e.preventDefault();
            confirmSubmit(e.target);
        }
    });
};
