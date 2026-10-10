/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Find Near Me (resources/views/components/find-near-me.blade.php).
 * Asks the browser once for the visitor's current position, sends it in a
 * single POST JSON request to the server (ExploreController::nearMe), and
 * shows the server's nearest public results with their server-calculated
 * distance labels. Nothing is calculated here.
 *
 * Privacy: the position lives only in local variables for one request. It
 * is never written to localStorage, sessionStorage, IndexedDB, cookies, a
 * URL, a link, or analytics, and never sent anywhere but the iTOUR server.
 * A "find-near-me:results" event lets a page map plot the results; it gets
 * the position rounded to 4 decimals, for display only.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

/** One position request: no continuous tracking, a short timeout, a recent cached fix is fine. */
const GEOLOCATION_OPTIONS = { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 };

/** Decimal places of the approximate position handed to a page map (display only). */
const DISPLAY_COORDINATE_DECIMALS = 4;

/** Visitor-facing messages — plain language, never technical details. */
const MESSAGES = {
    locating: 'Finding your location…',
    searching: 'Looking for places near you…',
    unsupported: "Your browser can't share your location. You can still browse the full directory.",
    insecure: 'Location only works on a secure (https) connection. You can still browse the full directory.',
    denied: 'Location access was not allowed. You can still search the directory, filter by municipality or category, or open a destination and use Find Nearby.',
    unavailable: "Your location isn't available right now. Check that location services are on, then try again.",
    timeout: 'Finding your location took too long. Please try again.',
    invalid: "We couldn't use that location. Please try again.",
    rateLimited: 'Too many searches in a short time. Please wait a minute and try again.',
    expired: 'This page has expired. Please refresh it and try again.',
    serverError: "Nearby places can't be shown right now. Please try again later.",
    offline: "You seem to be offline. Check your connection and try again.",
    notNow: 'No problem. You can search the directory, or open a destination and use Find Nearby.',
};

/**
 * One-time position request (no continuous tracking). Resolves with the
 * visitor's coordinates, or rejects with an Error whose message is a plain,
 * visitor-facing sentence. The coordinates are not kept anywhere.
 */
export function requestCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!window.isSecureContext) {
            reject(new Error(MESSAGES.insecure));
            return;
        }

        if (!('geolocation' in navigator)) {
            reject(new Error(MESSAGES.unsupported));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
            (error) => reject(new Error(geolocationMessage(error))),
            GEOLOCATION_OPTIONS,
        );
    });
}

/**
 * Sends a location to the iTOUR server only, in the POST JSON body, and
 * resolves with the server's nearest public places. Rejects with an Error
 * carrying a plain visitor-facing message.
 *
 * @param {string} endpoint The Find Near Me route.
 * @param {{latitude: number, longitude: number, radius?: number, category?: ?string, page?: number}} payload
 */
export async function requestNearbyPlaces(endpoint, payload) {
    let response;

    try {
        response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(payload),
        });
    } catch {
        throw new Error(MESSAGES.offline);
    }

    const data = await response.json().catch(() => null);

    if (response.ok && data) {
        return data;
    }

    throw new Error(failureMessage(response.status, data));
}

/**
 * Every Find Near Me panel on the page.
 */
export function initFindNearMe() {
    document.querySelectorAll('[data-find-near-me]').forEach((root) => initPanel(root));
}

function initPanel(root) {
    const endpoint = root.dataset.endpoint;
    const radiusSelect = root.querySelector('[data-find-near-me-radius]');
    const categorySelect = root.querySelector('[data-find-near-me-category]');
    const allowButton = root.querySelector('[data-find-near-me-allow]');
    const allowLabel = root.querySelector('[data-find-near-me-allow-label]');
    const dismissButton = root.querySelector('[data-find-near-me-dismiss]');
    const status = root.querySelector('[data-find-near-me-status]');
    const actions = root.querySelector('[data-find-near-me-actions]');
    const widerButton = root.querySelector('[data-find-near-me-wider]');
    const results = root.querySelector('[data-find-near-me-results]');
    const note = root.querySelector('[data-find-near-me-note]');
    let isBusy = false;

    if (!endpoint || !radiusSelect || !allowButton || !status || !results) {
        return;
    }

    allowButton.addEventListener('click', () => search());
    dismissButton?.addEventListener('click', () => {
        clearResults();
        showStatus(MESSAGES.notNow, { showActions: true });
    });
    widerButton?.addEventListener('click', () => {
        const nextOption = nextRadiusOption();

        if (nextOption) {
            radiusSelect.value = nextOption.value;
            search();
        }
    });

    /**
     * One search: a fresh one-time position request, then one POST.
     */
    async function search() {
        if (isBusy) {
            return;
        }

        clearResults();
        setBusy(true);
        showStatus(MESSAGES.locating);

        try {
            const { latitude, longitude } = await requestCurrentPosition();

            showStatus(MESSAGES.searching);

            const data = await requestNearbyPlaces(endpoint, {
                latitude,
                longitude,
                radius: Number(radiusSelect.value),
                category: categorySelect?.value || null,
            });

            setBusy(false);
            renderResults(data, latitude, longitude);
        } catch (error) {
            setBusy(false);
            showStatus(error.message, { showActions: true });
        }
    }

    function renderResults(data, latitude, longitude) {
        const places = Array.isArray(data.results) ? data.results : [];

        if (places.length === 0) {
            showStatus(`No tourism services were found within ${data.radiusKm} km of you.`, { showActions: true, offerWider: true });
            announce(places, latitude, longitude);
            return;
        }

        places.forEach((place) => results.appendChild(resultRow(place)));
        results.hidden = false;
        note.hidden = false;

        const shown = places.length < data.total ? `Showing the nearest ${places.length} of ${data.total}` : `${data.total} ${data.total === 1 ? 'place' : 'places'}`;
        showStatus(`${shown} within ${data.radiusKm} km of you, nearest first.`);
        allowLabel.textContent = 'Search again';
        announce(places, latitude, longitude);
    }

    /**
     * Lets a page map plot the results. The position is rounded for
     * display and stays in the browser.
     */
    function announce(places, latitude, longitude) {
        root.dispatchEvent(new CustomEvent('find-near-me:results', {
            bubbles: true,
            detail: {
                places,
                origin: {
                    lat: Number(latitude.toFixed(DISPLAY_COORDINATE_DECIMALS)),
                    lng: Number(longitude.toFixed(DISPLAY_COORDINATE_DECIMALS)),
                },
            },
        }));
    }

    function clearResults() {
        results.replaceChildren();
        results.hidden = true;

        if (note) note.hidden = true;
        if (actions) actions.hidden = true;
        if (widerButton) widerButton.hidden = true;

        root.dispatchEvent(new CustomEvent('find-near-me:cleared', { bubbles: true }));
    }

    function showStatus(message, { showActions = false, offerWider = false } = {}) {
        status.textContent = message;
        status.hidden = !message;

        if (actions) actions.hidden = !showActions;

        if (widerButton) {
            const nextOption = nextRadiusOption();
            widerButton.hidden = !(offerWider && nextOption);

            if (nextOption) {
                widerButton.textContent = `Search within ${nextOption.textContent}`;
            }
        }
    }

    function setBusy(busy) {
        isBusy = busy;
        allowButton.disabled = busy;
    }

    function nextRadiusOption() {
        return radiusSelect.options[radiusSelect.selectedIndex + 1] ?? null;
    }
}

/**
 * One result row, built with textContent only (no HTML from data).
 */
function resultRow(place) {
    const item = document.createElement('li');
    item.className = 'flex items-start justify-between gap-3 px-3 py-2.5';

    const text = document.createElement('div');
    text.className = 'min-w-0';

    const link = document.createElement('a');
    link.href = place.url;
    link.className = 'font-semibold text-sand-900 hover:text-primary-700';
    link.textContent = place.name;

    const meta = document.createElement('p');
    meta.className = 'text-xs text-sand-500';
    meta.textContent = [place.subtype || place.category, place.municipality].filter(Boolean).join(' · ');

    text.append(link, meta);

    const distance = document.createElement('p');
    distance.className = 'shrink-0 text-xs font-semibold text-sand-700';
    distance.textContent = place.distanceLabel;

    item.append(text, distance);

    return item;
}

function geolocationMessage(error) {
    switch (error?.code) {
        case 1:
            return MESSAGES.denied;
        case 2:
            return MESSAGES.unavailable;
        case 3:
            return MESSAGES.timeout;
        default:
            return MESSAGES.unavailable;
    }
}

/**
 * A server answer that was not a result list, as a plain message. A
 * location outside Davao Oriental comes back as a validation error on
 * "location" with its own wording.
 */
function failureMessage(statusCode, data) {
    switch (statusCode) {
        case 422:
            return data?.errors?.location?.[0] ?? MESSAGES.invalid;
        case 429:
            return MESSAGES.rateLimited;
        case 419:
            return MESSAGES.expired;
        default:
            return MESSAGES.serverError;
    }
}
