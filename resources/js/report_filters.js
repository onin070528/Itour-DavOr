/**
 * Report filter forms: a <select data-auto-submit> submits its form as soon
 * as it changes (e.g. the Manual Entry reporting-month picker), so the page
 * needs no separate "Show" button. Without JavaScript the form's own
 * submit button (in <noscript>) still works.
 */
export function initAutoSubmitSelects() {
    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.requestSubmit());
    });
}
