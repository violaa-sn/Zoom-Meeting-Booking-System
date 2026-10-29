export const badge = (text, tone) =>
  Object.assign(document.createElement("span"), {
    className: `badge rounded-pill badge-soft-${tone}`,
    textContent: text,
  });
