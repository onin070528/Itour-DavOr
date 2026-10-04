/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Show/hide/remember the "Storage & Cache Usage" notice
 * (resources/views/components/storage-notice.blade.php) on public pages.
 * Remembers one choice in one localStorage key; bumping NOTICE_VERSION
 * re-shows the notice to everyone. If localStorage is blocked, the notice
 * simply shows every visit instead — it must never break the page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

const STR_STORAGE_KEY = 'itour_storage_notice';
const NOTICE_VERSION = 1;

document.addEventListener('DOMContentLoaded', initStorageNotice);

/**
 * Wires up the notice: shows it unless a current-version choice is already
 * stored, and binds Dismiss/I Understand/Escape to remember that choice.
 */
function initStorageNotice() {
    const objNotice = document.getElementById('storage-notice');
    if (!objNotice) {
        return;
    }

    const blnHasChoice = readStoredChoice();
    if (blnHasChoice) {
        return;
    }

    showNotice(objNotice);

    document.getElementById('storage-notice-dismiss')
        ?.addEventListener('click', () => acknowledge(objNotice, 'dismissed'));

    document.getElementById('storage-notice-understand')
        ?.addEventListener('click', () => acknowledge(objNotice, 'understood'));

    objNotice.addEventListener('keydown', (objEvent) => {
        if (objEvent.key === 'Escape') {
            acknowledge(objNotice, 'dismissed');
        }
    });
}

/**
 * Reads the single stored choice. Any failure (storage blocked, corrupt
 * value, version mismatch) is treated as "no choice yet" rather than
 * thrown — the notice shows again, nothing breaks.
 */
function readStoredChoice() {
    try {
        const strRaw = window.localStorage.getItem(STR_STORAGE_KEY);
        if (!strRaw) {
            return false;
        }

        const objStored = JSON.parse(strRaw);
        return objStored.version === NOTICE_VERSION;
    } catch {
        return false;
    }
}

/**
 * Records the visitor's choice and hides the notice. Storage failures are
 * swallowed on purpose (see readStoredChoice) — worst case, the notice
 * reappears next visit.
 */
function acknowledge(objNotice, strChoice) {
    try {
        window.localStorage.setItem(STR_STORAGE_KEY, JSON.stringify({
            version: NOTICE_VERSION,
            choice: strChoice,
            date: new Date().toISOString(),
        }));
    } catch {
        // Intentionally ignored — see function doc above.
    }

    hideNotice(objNotice);
}

function showNotice(objNotice) {
    objNotice.hidden = false;
    objNotice.classList.remove('hidden');
    objNotice.classList.add('flex');
}

function hideNotice(objNotice) {
    objNotice.hidden = true;
    objNotice.classList.add('hidden');
    objNotice.classList.remove('flex');
}
