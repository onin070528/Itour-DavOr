/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public tourist feedback form (Objective 4) — a live character
 * counter for the feedback text, and a submit button that disables itself
 * once the form is sent, so a double tap cannot post the same feedback
 * twice. The server still validates everything and blocks duplicates.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

/**
 * Wires up every [data-feedback-form] on the page; does nothing elsewhere.
 */
export function initTouristFeedbackForm() {
    document.querySelectorAll('[data-feedback-form]').forEach((form) => {
        const textArea = form.querySelector('[data-feedback-text]');
        const counter = form.querySelector('[data-feedback-count]');
        const submitButton = form.querySelector('[data-feedback-submit]');

        if (textArea && counter) {
            const updateCount = () => {
                counter.textContent = String(textArea.value.length);
            };

            textArea.addEventListener('input', updateCount);
            updateCount();
        }

        form.addEventListener('submit', () => {
            if (submitButton) {
                submitButton.disabled = true;
            }
        });
    });
}
