import { registerSW } from 'virtual:pwa-register';
import { initFindNearMe, requestCurrentPosition, requestNearbyPlaces } from './find_near_me';
import { initTouristFeedbackForm } from './tourist_feedback_form';

// Registers the service worker configured in vite.config.js (VitePWA) —
// offline caching for the public Hotlines page only (see its runtimeCaching
// rule), not an app-wide offline mode.
registerSW({ immediate: true });

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPasswordToggle();
    initPasswordChecklist();
    initLoginForm();
    initExploreMap();
    initListingDetailMap();
    initFindNearMe();
    initNearbyMap();
    initListingDetailsModal();
    initExperiencesExploreAll();
    initChatbot();
    initEstablishmentQrForm();
    initHeroCarousel();
    initTouristFeedbackForm();
});

/**
 * Landing page hero background carousel: cross-fades between
 * [data-hero-slide] images every 6s (opacity-only, no layout shift —
 * transition-opacity/duration-1000 already set on each slide in the
 * Blade markup), updates [data-hero-indicator] pagination to match, and
 * pauses autoplay while the hero search input has focus so the background
 * doesn't change mid-type. No-ops entirely on pages without a
 * [data-hero-carousel] section.
 */
function initHeroCarousel() {
    const root = document.querySelector('[data-hero-carousel]');
    if (!root) return;

    const slides = Array.from(root.querySelectorAll('[data-hero-slide]'));
    const indicators = Array.from(root.querySelectorAll('[data-hero-indicator]'));
    const searchInput = document.getElementById('hero-search');

    if (!slides.length) return;

    const AUTOPLAY_MS = 6000;
    let current = 0;
    let timer = null;

    function goToSlide(index) {
        current = (index + slides.length) % slides.length;

        slides.forEach((slide, i) => {
            slide.classList.toggle('opacity-100', i === current);
            slide.classList.toggle('opacity-0', i !== current);
        });

        indicators.forEach((indicator, i) => {
            const active = i === current;
            indicator.setAttribute('aria-selected', String(active));
            indicator.classList.toggle('w-6', active);
            indicator.classList.toggle('bg-white', active);
            indicator.classList.toggle('w-2', !active);
            indicator.classList.toggle('bg-white/40', !active);
        });

    }

    // Always clearing any existing interval before starting a new one, so a
    // stray extra call (e.g. focus/blur firing in quick succession) can
    // never leave two autoplay timers running at once.
    function startAutoplay() {
        stopAutoplay();
        timer = setInterval(() => goToSlide(current + 1), AUTOPLAY_MS);
    }

    function stopAutoplay() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    indicators.forEach((indicator, i) => {
        indicator.addEventListener('click', () => {
            goToSlide(i);
            // Manual navigation restarts the 6s window from here, rather
            // than changing slides again almost immediately.
            startAutoplay();
        });
    });

    searchInput?.addEventListener('focus', stopAutoplay);
    searchInput?.addEventListener('blur', startAutoplay);

    startAutoplay();
}

function initMobileMenu() {
    const menuButton = document.getElementById('mobile-menu-button');
    const menu = document.getElementById('mobile-menu');
    const iconOpen = document.getElementById('mobile-menu-icon-open');
    const iconClose = document.getElementById('mobile-menu-icon-close');

    if (!menuButton || !menu) {
        return;
    }

    menuButton.addEventListener('click', () => {
        const isOpen = menu.classList.toggle('hidden') === false;

        menuButton.setAttribute('aria-expanded', String(isOpen));
        iconOpen?.classList.toggle('hidden', isOpen);
        iconClose?.classList.toggle('hidden', !isOpen);
    });
}

/**
 * Password visibility toggle: `[data-password-toggle]` flips its nearest
 * preceding `input[type=password]` to `type=text` and swaps its icon.
 * Used on the login form.
 */
function initPasswordToggle() {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const input = toggle.closest('[data-password-field]')?.querySelector('input');
        const showIcon = toggle.querySelector('[data-icon-show]');
        const hideIcon = toggle.querySelector('[data-icon-hide]');
        if (!input) return;

        toggle.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isPassword));
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            showIcon?.classList.toggle('hidden', isPassword);
            hideIcon?.classList.toggle('hidden', !isPassword);
        });
    });
}

/**
 * Live password checklist (<x-auth.password-requirements>): ticks each rule
 * as the user types in the input it describes. Mirrors Password::defaults()
 * in AppServiceProvider — min 12 characters plus a letter, a number and a
 * symbol, using the same Unicode classes as Laravel's Password rule. A
 * visual guide only; the server validation stays the final authority.
 */
const PASSWORD_RULES = {
    length: (value) => [...value].length >= 12,
    letter: (value) => /\p{L}/u.test(value),
    number: (value) => /\p{N}/u.test(value),
    symbol: (value) => /[\p{Z}\p{S}\p{P}]/u.test(value),
};

function initPasswordChecklist() {
    document.querySelectorAll('[data-password-checklist]').forEach((checklist) => {
        const input = document.getElementById(checklist.dataset.passwordChecklist);
        if (!input) return;

        const items = checklist.querySelectorAll('[data-rule]');

        const update = () => {
            items.forEach((item) => {
                const isMet = PASSWORD_RULES[item.dataset.rule]?.(input.value) ?? false;
                item.dataset.met = String(isMet);
                item.querySelector('[data-icon-met]')?.classList.toggle('hidden', !isMet);
                item.querySelector('[data-icon-unmet]')?.classList.toggle('hidden', isMet);

                const state = item.querySelector('[data-rule-state]');
                if (state) state.textContent = isMet ? '(met)' : '(not met yet)';
            });
        };

        input.addEventListener('input', update);
        update();
    });
}

/**
 * Login form (resources/views/auth/login.blade.php): inline client-side
 * validation (required + email format) surfaced in the same aria-live
 * region the server-rendered error uses, so the message styling and
 * screen-reader announcement behavior are identical either way. The form
 * has `novalidate` specifically so we control that message instead of the
 * browser's own validation bubble. Once client-side checks pass, the form
 * submits normally (no fetch/AJAX — this is a real POST to /login) and a
 * loading state is shown on the button while that navigation completes.
 */
function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;

    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const errorEl = document.getElementById('login-error');
    const submitButton = document.getElementById('login-submit');
    const submitLabel = submitButton?.querySelector('[data-submit-label]');
    const submitSpinner = submitButton?.querySelector('[data-submit-spinner]');

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function clearError() {
        errorEl?.classList.add('hidden');
    }

    [emailInput, passwordInput].forEach((input) => {
        input?.addEventListener('input', () => {
            input.removeAttribute('aria-invalid');
            clearError();
        });
    });

    form.addEventListener('submit', (event) => {
        if (!emailInput.checkValidity()) {
            event.preventDefault();
            emailInput.setAttribute('aria-invalid', 'true');
            emailInput.focus();
            showError(emailInput.validity.valueMissing ? 'Please enter your email address.' : 'Please enter a valid email address.');

            return;
        }

        if (!passwordInput.checkValidity()) {
            event.preventDefault();
            passwordInput.setAttribute('aria-invalid', 'true');
            passwordInput.focus();
            showError('Please enter your password.');

            return;
        }

        // Validation passed — the browser proceeds with the real POST
        // submission below; this only shows a loading state on the button
        // while that navigation is in flight.
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }
        if (submitLabel) submitLabel.textContent = 'Signing in…';
        submitSpinner?.classList.remove('hidden');
    });
}

/**
 * Marker colors per kind on the public maps — keep in step with
 * resources/views/components/map-legend.blade.php. Maps only display
 * places; every distance shown comes from the server (NearbySearchService).
 */
const MAP_MARKER_COLORS = {
    reference: '#b8442f',
    destination: '#125d5a',
    establishment: '#cb6e30',
};

/**
 * Reveals the public "Map can't be displayed right now." message inside the
 * map's frame ([data-map-frame] > [data-map-fallback]).
 */
function showMapFallback(mapContainer) {
    const fallback = mapContainer.closest('[data-map-frame]')?.querySelector('[data-map-fallback]');

    if (fallback) {
        fallback.hidden = false;
    }
}

/**
 * Logs map errors; a failure before the map has loaded (for example a
 * rejected token or an unreachable style) also shows the fallback message.
 * Later tile errors are only logged, so a working map is never covered.
 */
function watchMapLoadFailure(map, mapContainer, logPrefix) {
    let isLoaded = false;

    map.on('load', () => {
        isLoaded = true;
    });
    map.on('error', (event) => {
        console.error(`${logPrefix} map error:`, event?.error ?? event);

        if (!isLoaded) {
            showMapFallback(mapContainer);
        }
    });
}

/**
 * One keyboard-accessible marker with a popup. Mapbox/GeoJSON order is
 * [longitude, latitude].
 */
function addPlaceMarker(map, place, color, popupHtml, options = {}) {
    const marker = new mapboxgl.Marker({ color, scale: options.scale ?? 1 })
        .setLngLat([place.lng, place.lat])
        .setPopup(new mapboxgl.Popup({ offset: 24, maxWidth: '260px' }).setHTML(popupHtml))
        .addTo(map);

    const element = marker.getElement();
    element.setAttribute('role', 'button');
    element.setAttribute('tabindex', '0');
    element.setAttribute('aria-label', options.label ?? place.name);
    element.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            marker.togglePopup();
        }
    });

    return marker;
}

/**
 * Highlights the list row ([data-map-item]) of the selected place, clearing
 * the others — the list/map link in both directions.
 */
function highlightMapItem(slug) {
    document.querySelectorAll('[data-map-item]').forEach((item) => {
        item.classList.toggle('bg-primary-100', item.dataset.mapItem === slug);
    });
}

/**
 * "Show on map" buttons ([data-map-focus]): bring the map into view, move to
 * the marker once (no looping animation), open its popup, and highlight the row.
 */
function wireMapFocusButtons(map, markersBySlug, mapContainer) {
    document.querySelectorAll('[data-map-focus]').forEach((button) => {
        button.addEventListener('click', () => {
            const marker = markersBySlug.get(button.dataset.mapFocus);

            if (!marker) {
                return;
            }

            mapContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            map.easeTo({ center: marker.getLngLat(), zoom: Math.max(map.getZoom(), 14) });

            if (!marker.getPopup().isOpen()) {
                marker.togglePopup();
            }

            highlightMapItem(button.dataset.mapFocus);
        });
    });
}

/**
 * Without a working map, "Show on map" has nothing to show — hide those
 * buttons; the listings stay reachable through their normal links.
 */
function hideMapFocusButtons() {
    document.querySelectorAll('[data-map-focus]').forEach((button) => {
        button.hidden = true;
    });
}

/**
 * The /explore Map view (resources/views/explore.blade.php): plots the
 * current page's listings. The server has already searched, filtered, and
 * paginated them (App\Http\Controllers\ExploreController) — nothing is
 * filtered and no distance is calculated here, and the page's JSON carries
 * only public pin fields. Without Mapbox (no public token, no WebGL) it
 * draws the illustrative province map; if Mapbox fails to load it shows the
 * "Map can't be displayed" message. The list under the map always works.
 */
function initExploreMap() {
    const dataEl = document.getElementById('explore-data');
    const mapCanvas = document.getElementById('explore-map-canvas');

    if (!dataEl || !mapCanvas) {
        return;
    }

    const { places, total, municipalities } = JSON.parse(dataEl.textContent);
    const caption = document.getElementById('explore-map-caption');
    const unplotted = total - places.length;
    const mapboxToken = mapCanvas.dataset.mapboxToken;
    const canUseMapbox = Boolean(window.mapboxgl && mapboxToken && mapboxgl.supported());

    if (!canUseMapbox) {
        console.error('[explore-map] mapbox-gl unavailable, no public token, or no WebGL — using the illustrative map.');
        hideMapFocusButtons();
        renderIllustrativeMap();
        return;
    }

    let mapboxMap;

    try {
        mapboxgl.accessToken = mapboxToken;
        mapboxMap = new mapboxgl.Map({
            container: mapCanvas,
            style: MAP_STYLES.satellite,
            center: [Number(mapCanvas.dataset.mapboxCenterLng), Number(mapCanvas.dataset.mapboxCenterLat)],
            zoom: 8.4,
        });
    } catch (error) {
        console.error('[explore-map] mapboxgl.Map() threw:', error);
        hideMapFocusButtons();
        showMapFallback(mapCanvas);
        return;
    }

    mapboxMap.addControl(new MapStyleToggleControl(), 'top-left');
    mapboxMap.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
    watchMapLoadFailure(mapboxMap, mapCanvas, '[explore-map]');

    const markersBySlug = new Map();

    places.forEach((place) => {
        const marker = addPlaceMarker(
            mapboxMap,
            place,
            MAP_MARKER_COLORS[place.kind] ?? MAP_MARKER_COLORS.establishment,
            `
                <span class="relative mb-2 block h-24 w-full overflow-hidden rounded-sm">${listingPhotoMarkup(place, 'h-24 w-full rounded-sm object-cover')}</span>
                <p class="font-semibold text-sand-900">${escapeHtml(place.name)}</p>
                <p class="text-xs text-sand-600">${escapeHtml(place.categoryLabel)} · ${escapeHtml(place.barangay)}, ${escapeHtml(place.municipality)}</p>
                <a href="${escapeHtml(place.href)}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">View Details<i class="ti ti-arrow-right"></i></a>
            `,
            { label: `${place.name} — ${place.categoryLabel}` },
        );
        marker.getElement().addEventListener('click', () => highlightMapItem(place.slug));
        markersBySlug.set(place.slug, marker);
    });

    wireMapFocusButtons(mapboxMap, markersBySlug, mapCanvas);

    if (caption) {
        caption.textContent = unplotted > 0
            ? `Pins mark ${places.length} of the ${total} listings on this page — ${unplotted} ${unplotted === 1 ? "doesn't" : "don't"} have a map location yet.`
            : 'Pins mark the listings on this page — select one for details.';
    }

    if (places.length === 1) {
        mapboxMap.jumpTo({ center: [places[0].lng, places[0].lat], zoom: 13 });
    } else if (places.length > 1) {
        const bounds = new mapboxgl.LngLatBounds();
        places.forEach((place) => bounds.extend([place.lng, place.lat]));
        mapboxMap.fitBounds(bounds, { padding: 60, maxZoom: 13, duration: 0 });
    }

    /**
     * Not-to-scale province sketch: municipality labels plus one pin per
     * listing on this page, placed at its municipality.
     */
    function renderIllustrativeMap() {
        const positionOf = (name) => municipalities.find((municipality) => municipality.name === name);
        const municipalityLabels = municipalities.map((municipality) => `
            <div class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center gap-1 text-[10px] font-medium text-sand-500" style="top:${municipality.top}%; left:${municipality.left}%;">
                <span class="h-1.5 w-1.5 rounded-full bg-sand-400"></span>${escapeHtml(municipality.name)}
            </div>
        `).join('');

        const seenPerMunicipality = {};
        const pins = places.map((place) => {
            const position = positionOf(place.municipality);
            if (!position) return '';

            const seen = seenPerMunicipality[place.municipality] ?? 0;
            seenPerMunicipality[place.municipality] = seen + 1;
            const jitterTop = position.top + (seen % 3) * 2.2 - 2.2;
            const jitterLeft = position.left + Math.floor(seen / 3) * 2.5;

            return `
                <a href="${escapeHtml(place.href)}" class="absolute -translate-x-1/2 -translate-y-full drop-shadow" style="top:${jitterTop}%; left:${jitterLeft}%; color:${MAP_MARKER_COLORS[place.kind] ?? MAP_MARKER_COLORS.establishment};" title="${escapeHtml(place.name)} — ${escapeHtml(place.categoryLabel)}" aria-label="${escapeHtml(place.name)}">
                    <i class="ti ti-map-pin text-2xl" aria-hidden="true"></i>
                </a>
            `;
        }).join('');

        mapCanvas.innerHTML = municipalityLabels + pins;

        if (caption) {
            caption.textContent = 'Illustrative province map — not to scale. Pins mark the municipality of each listing on this page.';
        }
    }
}

/**
 * Destination detail map (resources/views/listing-detail.blade.php): the
 * destination at its stored coordinates plus the nearby listings the
 * server already found (App\Services\NearbySearchService), each with its
 * server-calculated distance label. Rendered only when the destination has
 * a valid location; shows the "Map can't be displayed" message when Mapbox
 * is unavailable or fails. The nearby list below always works without it.
 */
function initListingDetailMap() {
    const mapContainer = document.getElementById('listing-map');
    const dataEl = document.getElementById('listing-map-data');

    if (!mapContainer || !dataEl) {
        return;
    }

    const { reference, places } = JSON.parse(dataEl.textContent);
    const mapboxToken = mapContainer.dataset.mapboxToken;

    if (!window.mapboxgl || !mapboxToken || !mapboxgl.supported()) {
        console.error('[listing-map] mapbox-gl unavailable, no public token, or no WebGL.');
        hideMapFocusButtons();
        showMapFallback(mapContainer);
        return;
    }

    let map;

    try {
        mapboxgl.accessToken = mapboxToken;
        map = new mapboxgl.Map({
            container: mapContainer,
            style: MAP_STYLES.satellite,
            center: [reference.lng, reference.lat],
            zoom: 13,
        });
    } catch (error) {
        console.error('[listing-map] mapboxgl.Map() threw:', error);
        hideMapFocusButtons();
        showMapFallback(mapContainer);
        return;
    }

    map.addControl(new MapStyleToggleControl(), 'top-left');
    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
    watchMapLoadFailure(map, mapContainer, '[listing-map]');

    addPlaceMarker(
        map,
        reference,
        MAP_MARKER_COLORS.reference,
        `<p class="font-semibold text-sand-900">${escapeHtml(reference.name)}</p><p class="text-xs text-sand-600">This destination</p>`,
        { scale: 1.2, label: `${reference.name} — this destination` },
    );

    const markersBySlug = new Map();

    places.forEach((place) => {
        const marker = addPlaceMarker(
            map,
            place,
            MAP_MARKER_COLORS[place.kind] ?? MAP_MARKER_COLORS.establishment,
            `
                <p class="font-semibold text-sand-900">${escapeHtml(place.name)}</p>
                <p class="text-xs text-sand-600">${escapeHtml(place.categoryLabel)} · ${escapeHtml(place.distanceLabel)}</p>
                <a href="${escapeHtml(place.href)}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">View Details<i class="ti ti-arrow-right"></i></a>
            `,
            { label: `${place.name} — ${place.categoryLabel}, ${place.distanceLabel}` },
        );
        marker.getElement().addEventListener('click', () => highlightMapItem(place.slug));
        markersBySlug.set(place.slug, marker);
    });

    wireMapFocusButtons(map, markersBySlug, mapContainer);

    if (places.length > 0) {
        const bounds = new mapboxgl.LngLatBounds([reference.lng, reference.lat], [reference.lng, reference.lat]);
        places.forEach((place) => bounds.extend([place.lng, place.lat]));
        map.fitBounds(bounds, { padding: 50, maxZoom: 15, duration: 0 });
    }
}

/**
 * The public Nearby page (resources/views/nearby.blade.php): a Mapbox map and
 * a list of the province's public places. Which places are nearest, and how
 * far they are, comes only from the server (POST /find-near-me ->
 * NearbySearchService, reached through requestNearbyPlaces()); this page
 * calculates no distance. Without a chosen location it shows every public
 * place, filtered by category for display only. The chosen location (device
 * position or a searched place) lives in this function's memory only — it is
 * never stored — and "Clear location" drops it. Mapbox order is [lng, lat].
 */
function initNearbyMap() {
    const page = document.getElementById('nearby-page');
    const container = document.getElementById('nearby-map');
    const dataEl = document.getElementById('nearby-map-data');

    if (!page || !container || !dataEl) {
        return;
    }

    const places = JSON.parse(dataEl.textContent);
    const placesBySlug = new Map(places.map((place) => [place.slug, place]));
    const endpoint = page.dataset.endpoint;
    const mapboxToken = container.dataset.mapboxToken;

    const searchForm = document.getElementById('nearby-search-form');
    const searchInput = document.getElementById('nearby-search');
    const searchSubmit = document.getElementById('nearby-search-submit');
    const useLocationButton = document.getElementById('nearby-use-location');
    const useLocationLabel = document.getElementById('nearby-use-location-label');
    const clearButton = document.getElementById('nearby-clear-location');
    const radiusSelect = document.getElementById('nearby-radius');
    const statusEl = document.getElementById('nearby-status');
    const noticeEl = document.getElementById('nearby-notice');
    const listEl = document.getElementById('nearby-list');
    const distanceNote = document.getElementById('nearby-distance-note');
    const categoryButtons = Array.from(page.querySelectorAll('[data-nearby-category]'));
    const viewButtons = Array.from(page.querySelectorAll('[data-nearby-view]'));
    const panels = Array.from(page.querySelectorAll('[data-nearby-panel]'));

    const state = {
        origin: null, // { lat, lng, label } — in memory only
        category: '',
        results: [],
        total: 0,
        page: 1,
        lastPage: 1,
        error: null,
        isLoading: false,
        selected: null,
        requestId: 0,
    };

    const attr = (value) => escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

    // ---- Map -------------------------------------------------------------

    let map = null;
    const markers = new Map();
    let originMarker = null;

    if (window.mapboxgl && mapboxToken && mapboxgl.supported()) {
        try {
            mapboxgl.accessToken = mapboxToken;
            map = new mapboxgl.Map({
                container,
                style: MAP_STYLES.satellite,
                center: [Number(container.dataset.mapboxCenterLng), Number(container.dataset.mapboxCenterLat)],
                zoom: 8.4,
            });
            map.addControl(new MapStyleToggleControl(), 'top-left');
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
            watchMapLoadFailure(map, container, '[nearby-map]');
            map.on('load', () => map.resize());
        } catch (error) {
            console.error('[nearby-map] mapboxgl.Map() threw:', error);
            map = null;
            showMapFallback(container);
        }
    } else {
        console.error('[nearby-map] mapbox-gl unavailable, no public Mapbox token, or no WebGL.');
        showMapFallback(container);
    }

    // ---- What is shown ---------------------------------------------------

    /**
     * The list items for the current state: the server's nearest results
     * (enriched with the page's public fields) once a location is chosen,
     * otherwise every public place in the chosen category.
     */
    function currentItems() {
        if (state.origin) {
            return state.results.map((result) => {
                const base = placesBySlug.get(result.slug);

                return {
                    slug: result.slug,
                    name: result.name,
                    kind: result.type,
                    categoryLabel: result.subtype || result.category,
                    address: [result.barangay, result.municipality].filter(Boolean).join(', '),
                    lat: result.latitude,
                    lng: result.longitude,
                    hours: base?.hours ?? null,
                    href: result.url,
                    directionsUrl: base?.directionsUrl ?? null,
                    distanceLabel: result.distanceLabel,
                };
            });
        }

        return places
            .filter((place) => state.category === '' || place.category === state.category)
            .sort((first, second) => first.name.localeCompare(second.name))
            .map((place) => ({
                slug: place.slug,
                name: place.name,
                kind: place.kind,
                categoryLabel: place.categoryLabel,
                address: [place.barangay, place.municipality].filter(Boolean).join(', '),
                lat: place.lat,
                lng: place.lng,
                hours: place.hours,
                href: place.href,
                directionsUrl: place.directionsUrl,
                distanceLabel: null,
            }));
    }

    function render() {
        const items = state.error ? [] : currentItems();

        renderList(items);
        renderMarkers(items);
        renderStatus(items);

        clearButton.hidden = !state.origin;
        distanceNote.hidden = !state.origin || items.length === 0;
    }

    function renderList(items) {
        if (state.isLoading && state.results.length === 0) {
            listEl.innerHTML = Array.from({ length: 4 }, () => `
                <li class="animate-pulse rounded-md border border-sand-200 bg-sand-0 p-4" aria-hidden="true">
                    <div class="h-4 w-2/3 rounded-sm bg-sand-200"></div>
                    <div class="mt-2 h-3 w-1/3 rounded-sm bg-sand-100"></div>
                    <div class="mt-4 h-3 w-5/6 rounded-sm bg-sand-100"></div>
                    <div class="mt-4 flex gap-2"><div class="h-8 w-24 rounded-sm bg-sand-200"></div><div class="h-8 w-24 rounded-sm bg-sand-100"></div></div>
                </li>
            `).join('');
            listEl.setAttribute('aria-busy', 'true');
            return;
        }

        listEl.removeAttribute('aria-busy');

        if (state.error) {
            listEl.innerHTML = `
                <li class="rounded-md border border-sand-200 bg-sand-0 p-6 text-center">
                    <i class="ti ti-cloud-off text-3xl text-sand-400" aria-hidden="true"></i>
                    <p class="mt-2 text-sm font-semibold text-sand-900">${escapeHtml(state.error)}</p>
                    <button type="button" data-nearby-retry class="mt-3 inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Try again</button>
                </li>
            `;
            return;
        }

        if (items.length === 0) {
            const hasWiderRadius = Boolean(state.origin && radiusSelect.options[radiusSelect.selectedIndex + 1]);

            listEl.innerHTML = `
                <li class="rounded-md border border-sand-200 bg-sand-0 p-6 text-center">
                    <i class="ti ti-map-search text-3xl text-sand-400" aria-hidden="true"></i>
                    <p class="mt-2 text-sm font-semibold text-sand-900">${state.origin ? 'No places found near this location' : 'No places found in this category'}</p>
                    <p class="mt-1 text-xs text-sand-600">Try ${state.origin ? 'a wider distance, ' : ''}another category, or browse the full directory.</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        ${hasWiderRadius ? '<button type="button" data-nearby-wider class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Search a wider area</button>' : ''}
                        <a href="${attr(page.dataset.directoryUrl ?? '/explore')}" class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Browse the full directory</a>
                    </div>
                </li>
            `;
            return;
        }

        listEl.innerHTML = items.map((item) => `
            <li data-map-item="${attr(item.slug)}" data-nearby-card class="cursor-pointer rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm transition-colors hover:border-primary-300">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-display text-sm font-bold text-sand-900">${escapeHtml(item.name)}</h2>
                        <p class="mt-0.5 text-xs font-semibold text-primary-700">${escapeHtml(item.categoryLabel)}</p>
                    </div>
                    ${item.distanceLabel ? `<p class="shrink-0 rounded-sm bg-sand-100 px-2 py-1 text-xs font-semibold text-sand-700">${escapeHtml(item.distanceLabel)}</p>` : ''}
                </div>
                ${item.address ? `<p class="mt-2 flex items-start gap-1.5 text-xs text-sand-600"><i class="ti ti-map-pin mt-0.5 shrink-0" aria-hidden="true"></i>${escapeHtml(item.address)}</p>` : ''}
                ${item.hours ? `<p class="mt-1 flex items-start gap-1.5 text-xs text-sand-600"><i class="ti ti-clock mt-0.5 shrink-0" aria-hidden="true"></i>${escapeHtml(item.hours)}</p>` : ''}
                <div class="mt-3 flex flex-wrap gap-2">
                    ${placesBySlug.has(item.slug)
                        ? `<button type="button" data-listing-details="${attr(item.slug)}" class="inline-flex items-center gap-1 rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">View Details</button>`
                        : `<a href="${attr(item.href)}" class="inline-flex items-center gap-1 rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">View Details</a>`}
                    ${item.directionsUrl ? `<a href="${attr(item.directionsUrl)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300"><i class="ti ti-directions" aria-hidden="true"></i>Directions</a>` : ''}
                </div>
            </li>
        `).join('') + (state.origin && state.page < state.lastPage
            ? `<li class="text-center"><button type="button" data-nearby-more class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300 disabled:opacity-70">Show more places</button></li>`
            : '');

        paintSelection();
    }

    function renderStatus(items) {
        if (state.isLoading) {
            statusEl.textContent = state.origin ? 'Looking for places near this location…' : 'Loading places…';
            return;
        }

        if (state.error) {
            statusEl.textContent = '';
            return;
        }

        if (!state.origin) {
            statusEl.textContent = `Showing ${items.length} ${items.length === 1 ? 'place' : 'places'} across Davao Oriental. Search a location or use yours to see the closest.`;
            return;
        }

        if (items.length === 0) {
            statusEl.textContent = '';
            return;
        }

        const shown = items.length < state.total
            ? `Showing the nearest ${items.length} of ${state.total}`
            : `${state.total} ${state.total === 1 ? 'place' : 'places'}`;
        statusEl.textContent = `${shown} within ${radiusSelect.value} km of ${state.origin.label}, nearest first.`;
    }

    function renderMarkers(items) {
        if (!map) {
            return;
        }

        markers.forEach((marker) => marker.remove());
        markers.clear();
        originMarker?.remove();
        originMarker = null;

        items.forEach((item) => {
            const marker = addPlaceMarker(
                map,
                item,
                MAP_MARKER_COLORS[item.kind] ?? MAP_MARKER_COLORS.establishment,
                `
                    <p class="font-semibold text-sand-900">${escapeHtml(item.name)}</p>
                    <p class="text-xs text-sand-600">${escapeHtml(item.categoryLabel)}${item.distanceLabel ? ` · ${escapeHtml(item.distanceLabel)}` : ''}</p>
                `,
                { label: `${item.name} — ${item.categoryLabel}${item.distanceLabel ? `, ${item.distanceLabel}` : ''}` },
            );
            marker.getElement().addEventListener('click', () => selectPlace(item.slug, { fromMap: true }));
            markers.set(item.slug, marker);
        });

        if (state.origin) {
            originMarker = addPlaceMarker(
                map,
                { name: state.origin.label, lat: state.origin.lat, lng: state.origin.lng },
                MAP_MARKER_COLORS.reference,
                `<p class="font-semibold text-sand-900">${escapeHtml(state.origin.label)}</p>`,
                { scale: 1.1, label: `Search location: ${state.origin.label}` },
            );
        }

        const bounds = new mapboxgl.LngLatBounds();
        let hasPoints = false;

        if (state.origin) {
            bounds.extend([state.origin.lng, state.origin.lat]);
            hasPoints = true;
        }

        items.forEach((item) => {
            bounds.extend([item.lng, item.lat]);
            hasPoints = true;
        });

        if (hasPoints) {
            map.fitBounds(bounds, { padding: 50, maxZoom: state.origin ? 14 : 12, duration: 0 });
        }
    }

    // ---- Selection: marker <-> card --------------------------------------

    function paintSelection() {
        listEl.querySelectorAll('[data-nearby-card]').forEach((card) => {
            const isSelected = card.dataset.mapItem === state.selected;

            card.classList.toggle('bg-primary-100', isSelected);
            card.classList.toggle('border-primary-700', isSelected);
            card.classList.toggle('ring-1', isSelected);
            card.classList.toggle('ring-primary-700', isSelected);
        });
    }

    /**
     * Highlights the card of a place. From the map it scrolls the card into
     * view; from the list it moves the map to the marker and opens its popup.
     */
    function selectPlace(slug, { fromMap = false } = {}) {
        state.selected = slug;
        paintSelection();

        const card = Array.from(listEl.querySelectorAll('[data-nearby-card]')).find((element) => element.dataset.mapItem === slug);

        if (fromMap) {
            card?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        const marker = markers.get(slug);

        if (!map || !marker) {
            return;
        }

        if (!isDesktop()) {
            setView('map');
        }

        map.easeTo({ center: marker.getLngLat(), zoom: Math.max(map.getZoom(), 14) });

        if (!marker.getPopup().isOpen()) {
            marker.togglePopup();
        }
    }

    function setView(view) {
        viewButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.nearbyView === view)));
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.nearbyPanel !== view));

        if (view === 'map') {
            map?.resize();
        }
    }

    // ---- Location and server search --------------------------------------

    function showNotice(message) {
        noticeEl.textContent = message ?? '';
        noticeEl.hidden = !message;
    }

    function setBusy(isBusy, label = 'Use my location') {
        useLocationButton.disabled = isBusy;
        searchSubmit.disabled = isBusy;
        useLocationLabel.textContent = label;
    }

    /**
     * Sets the search location and asks the server for its nearest places.
     */
    function chooseLocation(lat, lng, label) {
        state.origin = { lat, lng, label };
        showNotice('');
        fetchResults(1);
    }

    async function fetchResults(pageNumber) {
        const requestId = ++state.requestId;
        const isAppend = pageNumber > 1;

        state.isLoading = !isAppend;
        state.error = null;

        if (!isAppend) {
            state.results = [];
        }

        render();

        try {
            const data = await requestNearbyPlaces(endpoint, {
                latitude: state.origin.lat,
                longitude: state.origin.lng,
                radius: Number(radiusSelect.value),
                category: state.category || null,
                page: pageNumber,
            });

            if (requestId !== state.requestId) return;

            state.results = isAppend ? [...state.results, ...data.results] : data.results;
            state.total = data.total;
            state.page = data.page;
            state.lastPage = data.lastPage;
        } catch (error) {
            if (requestId !== state.requestId) return;

            state.error = error.message;
        }

        state.isLoading = false;
        render();
    }

    async function useDeviceLocation() {
        showNotice('');
        setBusy(true, 'Locating…');

        try {
            const { latitude, longitude } = await requestCurrentPosition();

            // Rounded for display only, like the Find Near Me panel; the server rounds the search itself.
            chooseLocation(Number(latitude.toFixed(4)), Number(longitude.toFixed(4)), 'your location');
        } catch (error) {
            showNotice(error.message);
        }

        setBusy(false);
    }

    /**
     * Looks a typed place up through the Mapbox Geocoding API, limited to
     * Davao Oriental. Only the typed text goes to Mapbox — never a visitor
     * position.
     */
    async function searchPlace(query) {
        if (!mapboxToken) {
            showNotice("Place search isn't available right now. You can still use your location or pick a category.");
            return;
        }

        showNotice('');
        setBusy(true, 'Use my location');

        try {
            const params = new URLSearchParams({
                q: query,
                bbox: page.dataset.geocodeBounds,
                country: 'ph',
                limit: '1',
                access_token: mapboxToken,
            });
            const response = await fetch(`https://api.mapbox.com/search/geocode/v6/forward?${params}`);

            if (!response.ok) {
                throw new Error('geocoding failed');
            }

            const feature = (await response.json()).features?.[0];

            if (!feature) {
                showNotice(`We couldn't find "${query}" in Davao Oriental. Try a town, barangay, or landmark name.`);
            } else {
                const [lng, lat] = feature.geometry.coordinates;

                chooseLocation(lat, lng, feature.properties?.name ?? query);
            }
        } catch (error) {
            console.error('[nearby-map] place search failed:', error);
            showNotice("Place search isn't available right now. Please try again, or use your location.");
        }

        setBusy(false);
    }

    // ---- Wiring ----------------------------------------------------------

    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const query = searchInput.value.trim();

        if (query === '') {
            showNotice('Type a town, barangay, or landmark to search.');
            return;
        }

        searchPlace(query);
    });

    useLocationButton.addEventListener('click', useDeviceLocation);

    clearButton.addEventListener('click', () => {
        state.requestId++;
        state.origin = null;
        state.results = [];
        state.error = null;
        state.isLoading = false;
        state.selected = null;
        searchInput.value = '';
        showNotice('');
        render();
    });

    categoryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.category = button.dataset.nearbyCategory;
            categoryButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            state.selected = null;

            if (state.origin) {
                fetchResults(1);
            } else {
                render();
            }
        });
    });

    radiusSelect.addEventListener('change', () => {
        if (state.origin) {
            fetchResults(1);
        }
    });

    viewButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.nearbyView)));

    listEl.addEventListener('click', (event) => {
        if (event.target.closest('[data-nearby-retry]')) {
            fetchResults(1);
            return;
        }

        if (event.target.closest('[data-nearby-wider]')) {
            radiusSelect.selectedIndex += 1;
            fetchResults(1);
            return;
        }

        const moreButton = event.target.closest('[data-nearby-more]');

        if (moreButton) {
            moreButton.disabled = true;
            fetchResults(state.page + 1);
            return;
        }

        // Buttons and links inside a card do their own thing; the rest of the card selects its place.
        const card = event.target.closest('[data-nearby-card]');

        if (card && !event.target.closest('a, button')) {
            selectPlace(card.dataset.mapItem);
        }
    });

    setView('map');
    render();
}

/**
 * Base styles shared by every public Mapbox map: Satellite (real aerial
 * imagery with road/place labels) is the default, Streets the alternative.
 */
const MAP_STYLES = {
    satellite: 'mapbox://styles/mapbox/satellite-streets-v12',
    streets: 'mapbox://styles/mapbox/streets-v12',
};

/**
 * Mapbox control with "Satellite" / "Streets" buttons that swap the map's
 * base style. Markers are DOM elements and survive the swap; custom
 * sources/layers do not, so maps that add them must re-add them on
 * 'style.load'. Inline styles are used because mapbox-gl.css (unlayered)
 * overrides Tailwind utilities on .mapboxgl-ctrl-group buttons.
 */
class MapStyleToggleControl {
    onAdd(map) {
        this.container = document.createElement('div');
        this.container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group';
        this.container.style.display = 'flex';

        const buttons = Object.entries({ satellite: 'Satellite', streets: 'Streets' }).map(([key, label], index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.setAttribute('aria-pressed', String(index === 0));
            Object.assign(button.style, {
                width: 'auto',
                padding: '0 10px',
                fontSize: '12px',
                fontWeight: '600',
                borderTop: 'none',
                borderLeft: index === 0 ? 'none' : '1px solid #ddd',
            });
            button.addEventListener('click', () => {
                if (button.getAttribute('aria-pressed') === 'true') return;
                buttons.forEach((other) => paint(other, other === button));
                map.setStyle(MAP_STYLES[key]);
            });
            return button;
        });

        const paint = (button, active) => {
            button.setAttribute('aria-pressed', String(active));
            button.style.backgroundColor = active ? '#125d5a' : '';
            button.style.color = active ? '#fff' : '#333';
        };

        buttons.forEach((button, index) => {
            paint(button, index === 0);
            this.container.appendChild(button);
        });

        return this.container;
    }

    onRemove() {
        this.container.remove();
    }
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

/**
 * Shared cover-photo markup for every JS-rendered card (explore grid/table/
 * map popup): the cover photo when the listing has one, otherwise a
 * neutral category icon — never a stock/DOT placeholder photo (7E). Mirrors
 * resources/views/components/listing-photo.blade.php for server-rendered
 * cards.
 */
function listingPhotoMarkup(item, imgClass) {
    if (item.displayImageUrl) {
        return `<img src="${escapeHtml(item.displayImageUrl)}" alt="${escapeHtml(item.name)}" loading="lazy" class="${imgClass}">`;
    }

    return `<div class="${imgClass} flex items-center justify-center bg-sand-200"><i class="ti ${escapeHtml(item.categoryIcon)} text-3xl text-sand-400" aria-hidden="true"></i></div>`;
}

/**
 * Floating AI assistant widget (bottom-right on the public site). Handles
 * opening/closing the panel and appending messages to the thread.
 *
 * The bot reply below is a placeholder — once a real AI backend exists,
 * replace the setTimeout block with a fetch() to that endpoint.
 */
function initChatbot() {
    const toggle = document.getElementById('chatbot-toggle');
    const panel = document.getElementById('chatbot-panel');
    const closeButton = document.getElementById('chatbot-close');
    const toggleAvatar = document.getElementById('chatbot-toggle-avatar');
    const iconClose = document.getElementById('chatbot-toggle-icon-close');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');

    if (!toggle || !panel || !form || !input || !messages) {
        return;
    }

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        panel.classList.toggle('flex', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close chat with Ori' : 'Chat with Ori, your iTOUR tourism assistant');
        toggleAvatar?.classList.toggle('hidden', open);
        iconClose?.classList.toggle('hidden', !open);
        if (open) input.focus();
    };

    toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
    closeButton?.addEventListener('click', () => setOpen(false));

    const sendMessage = (text) => {
        appendMessage(text, 'user');

        window.setTimeout(() => {
            appendMessage(
                "Thanks for your message! I'm still being set up — in the meantime, try Explore to browse destinations, or check the Emergency contacts in the footer.",
                'bot'
            );
        }, 500);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        sendMessage(text);
    });

    messages.querySelectorAll('[data-chatbot-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            sendMessage(button.textContent.trim());
        });
    });

    function appendMessage(text, from) {
        const wrapper = document.createElement('div');
        const bubble = document.createElement('div');
        bubble.textContent = text;

        if (from === 'user') {
            wrapper.className = 'flex justify-end';
            bubble.className = 'max-w-[85%] rounded-md rounded-tr-none bg-primary-700 px-3 py-2 text-sm leading-relaxed text-sand-0';
            wrapper.appendChild(bubble);
        } else {
            wrapper.className = 'flex items-start gap-2';
            wrapper.innerHTML = '<span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-primary-100"><img src="/storage/itour-images/ori-chatbot-ai.png" alt="Ori" class="h-full w-full object-cover"></span>';
            bubble.className = 'max-w-[85%] rounded-md rounded-tl-none bg-sand-100 px-3 py-2 text-sm leading-relaxed text-sand-800';
            wrapper.appendChild(bubble);
        }

        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    }
}

/**
 * Establishment QR self-registration form (resources/views/lgu/establishmentQR.blade.php).
 * Wires up every +/- counter, keeps the live totals (header, per-group, and
 * the sticky submit bar) in sync, shows the "where is your group from"
 * fields only for the parts of the group that exist, and swaps in a
 * success step on submit.
 *
 * The headcount is a Foreign/Local x Male/Female x Adults/Children/Seniors
 * matrix — each cell's data-counter is "<group>-<gender>-<age>" (e.g.
 * "foreign-male-adults"). computeMatrixSums() parses that key on every cell to
 * roll the 12 granular counters back up into the 7 flat totals
 * (male/female/adults/children/seniors/local/foreign) CheckinController
 * validates and stores — every guest is counted exactly once per
 * dimension, so foreign+local always equals male+female.
 *
 * Double submit is blocked by an in-flight flag plus a disabled button;
 * server validation messages are shown inline above the submit button.
 */
function initEstablishmentQrForm() {
    const form = document.getElementById('establishment-qr-form');
    if (!form) return;

    const counters = Array.from(form.querySelectorAll('[data-counter]'));
    const byId = (id) => document.getElementById(id);
    const totalValue = byId('qr-total-value');
    const submitTotal = byId('qr-submit-total');
    const foreignValue = byId('qr-foreign-value');
    const localValue = byId('qr-local-value');
    const formStep = byId('qr-form-step');
    const successStep = byId('qr-success-step');
    const successSummary = byId('qr-success-summary');
    const resetButton = byId('qr-form-reset');
    const submitButton = byId('qr-submit');
    const errorBox = byId('qr-form-error');

    const originCard = byId('qr-origin-card');
    const localOriginWrap = byId('qr-local-origin-wrap');
    const municipalityWrap = byId('qr-local-origin-municipality-wrap');
    const municipalitySelect = byId('qr-local-origin-municipality');
    const provinceWrap = byId('qr-local-origin-place-wrap');
    const provinceInput = byId('qr-local-origin-place');
    const foreignCountryWrap = byId('qr-foreign-country-wrap');
    const foreignCountry = byId('qr-foreign-country');

    let isSubmitting = false;

    const readValue = (counter) => Number(counter.querySelector('[data-counter-value]').textContent) || 0;
    const writeValue = (counter, value) => {
        counter.querySelector('[data-counter-value]').textContent = String(Math.max(0, value));
    };
    const checkedValue = (name) => form.querySelector(`input[name="${name}"]:checked`)?.value ?? '';

    function computeMatrixSums() {
        const sums = { male: 0, female: 0, adults: 0, children: 0, seniors: 0, local: 0, foreign: 0 };
        counters.forEach((counter) => {
            const [group, gender, age] = counter.dataset.counter.split('-');
            const value = readValue(counter);
            sums[group] += value;
            sums[gender] += value;
            sums[age] += value;
        });
        return sums;
    }

    function showError(message) {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = message;
        errorBox.classList.toggle('hidden', !message);
    }

    function setSubmitting(submitting) {
        isSubmitting = submitting;
        if (!submitButton) {
            return;
        }
        submitButton.disabled = submitting;
        submitButton.querySelector('[data-submit-label]').textContent = submitting ? 'Submitting…' : 'Submit Registration';
        submitButton.querySelector('[data-submit-icon]').className = submitting
            ? 'ti ti-loader-2 animate-spin text-lg'
            : 'ti ti-clipboard-check text-lg';
    }

    function updateTotal() {
        const sums = computeMatrixSums();
        const total = sums.foreign + sums.local;
        const originScope = checkedValue('localOriginScope');

        totalValue.textContent = String(total);
        if (submitTotal) submitTotal.textContent = String(total);
        foreignValue.textContent = String(sums.foreign);
        localValue.textContent = String(sums.local);

        originCard?.classList.toggle('hidden', total <= 0);
        localOriginWrap?.classList.toggle('hidden', sums.local <= 0);
        foreignCountryWrap?.classList.toggle('hidden', sums.foreign <= 0);
        municipalityWrap?.classList.toggle('hidden', originScope !== 'within_province');
        provinceWrap?.classList.toggle('hidden', originScope !== 'outside_province');
    }

    // The place sent depends on the chosen scope: a Davao Oriental
    // municipality for "within", a province for "outside", nothing otherwise.
    function localOriginPlace(sums) {
        if (sums.local <= 0) return null;
        const originScope = checkedValue('localOriginScope');
        if (originScope === 'within_province') return municipalitySelect?.value || null;
        if (originScope === 'outside_province') return provinceInput?.value.trim() || null;
        return null;
    }

    // Where the group is from is required: local guests need within/outside
    // Davao Oriental plus the municipality or home province, and foreign
    // guests need their home country. Returns the message, or '' when complete.
    function originMissing(sums) {
        if (sums.local > 0) {
            const originScope = checkedValue('localOriginScope');
            if (!originScope) return 'Please tell us whether your local guests are from within or outside Davao Oriental.';
            if (!localOriginPlace(sums)) {
                return originScope === 'within_province'
                    ? 'Please choose the municipality or city your local guests are from.'
                    : 'Please enter the home province of your local guests.';
            }
        }
        if (sums.foreign > 0 && !foreignCountry?.value.trim()) {
            return 'Please enter the home country of your foreign guests.';
        }
        return '';
    }

    form.querySelectorAll('input[name="localOriginScope"]').forEach((radio) => radio.addEventListener('change', updateTotal));

    counters.forEach((counter) => {
        counter.querySelector('[data-counter-decrement]').addEventListener('click', () => {
            writeValue(counter, readValue(counter) - 1);
            updateTotal();
        });
        counter.querySelector('[data-counter-increment]').addEventListener('click', () => {
            writeValue(counter, readValue(counter) + 1);
            updateTotal();
            showError('');
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isSubmitting) return;
        showError('');

        const sums = computeMatrixSums();
        if (sums.male + sums.female < 1) {
            showError('Add at least one guest to the headcount — tap (+) for yourself and everyone with you.');
            return;
        }
        if (!form.reportValidity()) return;

        // Where the group is from is required for every non-empty part of it.
        const originError = originMissing(sums);
        if (originError) {
            showError(originError);
            return;
        }

        setSubmitting(true);
        let succeeded = false;

        try {
            // Counts live in [data-counter-value] spans, not real form
            // fields (see resources/views/components/lgu/qr-counter.blade.php),
            // so they're read directly rather than via FormData, then rolled
            // up from the 12-cell matrix into the 7 flat fields the backend
            // validates (see computeMatrixSums() above).
            const originScope = checkedValue('localOriginScope');
            const payload = {
                visitorName: form.elements.namedItem('visitorName')?.value.trim(),
                visitorContact: form.elements.namedItem('visitorContact')?.value.trim(),
                visitType: checkedValue('visitType'),
                website: form.elements.namedItem('website')?.value,
                remarks: form.elements.namedItem('remarks')?.value.trim() || null,
                localOriginScope: sums.local > 0 ? (originScope || null) : null,
                localOriginPlace: localOriginPlace(sums),
                foreignCountry: sums.foreign > 0 ? (foreignCountry?.value.trim() || null) : null,
                ...sums,
            };

            const response = await fetch(form.dataset.actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                const message = response.status === 429
                    ? 'Too many submissions in a short time. Please wait a minute and try again.'
                    : (firstError || body.message || "Couldn't submit your registration. Please try again.");
                showError(message);
                return;
            }

            succeeded = true;
            const visitLabel = payload.visitType === 'Overnight' ? 'Overnight' : 'Day Tour';
            const total = sums.local + sums.foreign;
            if (successSummary) successSummary.textContent = `${total} ${total === 1 ? 'person' : 'people'} · ${visitLabel}`;
            formStep?.classList.add('hidden');
            successStep?.classList.remove('hidden');
            successStep?.classList.add('flex');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch {
            showError("Couldn't submit your registration — please check your connection and try again.");
        } finally {
            // After a success the button stays disabled until "Register
            // Another Group", so a second tap can't save the group twice.
            if (!succeeded) setSubmitting(false);
        }
    });

    resetButton?.addEventListener('click', () => {
        form.reset();
        counters.forEach((counter) => writeValue(counter, 0));
        showError('');
        setSubmitting(false);
        updateTotal();
        successStep?.classList.add('hidden');
        successStep?.classList.remove('flex');
        formStep?.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    updateTotal();
}

/**
 * Landing page "View Details" modal (resources/views/components/
 * listing-details-modal.blade.php): any [data-listing-details="<id>"]
 * button opens one shared <dialog> filled from the embedded listings JSON.
 * Esc closes it natively; clicking the backdrop or a close button does too.
 */
function initListingDetailsModal() {
    const modal = document.getElementById('listing-details-modal');
    const dataEl = document.getElementById('listing-details-data');

    if (!modal || !dataEl) {
        return;
    }

    const listings = JSON.parse(dataEl.textContent);
    const field = (id) => document.getElementById(`listing-details-${id}`);

    // Shows the <img> when the listing has a cover photo, otherwise the
    // neutral category-icon placeholder — independent of showPhoto()/
    // showDirections() below, which toggle the whole photo-vs-map group.
    function applyPhotoVisibility(listing) {
        const hasPhoto = Boolean(listing?.displayImageUrl);
        field('image').hidden = !hasPhoto;
        field('placeholder').hidden = hasPhoto;
    }

    function open(listing) {
        if (listing.displayImageUrl) {
            field('image').src = listing.displayImageUrl;
            field('image').alt = `${listing.name}, ${listing.municipality}`;
        }
        field('placeholder-icon').className = `ti ${listing.categoryIcon} text-6xl text-sand-400`;
        applyPhotoVisibility(listing);
        field('full-page').href = listing.href;
        field('category').textContent = listing.categoryLabel;
        field('name').textContent = listing.name;
        field('rating').textContent = listing.rating !== null ? listing.rating.toFixed(1) : 'No ratings yet';
        field('location').textContent = `${listing.barangay}, ${listing.municipality}`;
        field('description').textContent = listing.description ?? '';

        field('tags').innerHTML = (listing.tags ?? []).map((tag) => `
            <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">${escapeHtml(tag)}</span>
        `).join('');

        modal.querySelectorAll('[data-listing-details-row]').forEach((row) => {
            const key = row.dataset.listingDetailsRow;
            const value = listing[key];
            const valueEl = row.querySelector('[data-listing-details-value]');

            row.hidden = !value;
            if (!value) return;

            if (key === 'contactPhone') {
                valueEl.innerHTML = `<a href="tel:${escapeHtml(value.replace(/[^\d+]/g, ''))}" class="hover:text-primary-700">${escapeHtml(value)}</a>`;
            } else if (key === 'email') {
                valueEl.innerHTML = `<a href="mailto:${escapeHtml(value)}" class="hover:text-primary-700">${escapeHtml(value)}</a>`;
            } else if (key === 'website') {
                const url = /^https?:\/\//i.test(value) ? value : `https://${value}`;
                valueEl.innerHTML = `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="text-primary-700 hover:text-primary-900">${escapeHtml(value)}</a>`;
            } else {
                valueEl.textContent = value;
            }
        });

        currentListing = listing;
        mapToggle.hidden = listing.lat === null || listing.lng === null;
        directionsLink.hidden = !listing.directionsUrl;
        directionsLink.href = listing.directionsUrl ?? '#';
        showPhoto();

        modal.showModal();
        document.body.classList.add('overflow-hidden');
    }

    // Location (Objective 3, Phase 6): "View on map" swaps the hero photo
    // for a Mapbox map of the destination only. "Get directions" is an
    // external link built on the server (App\Support\DirectionsLink) with
    // the destination's coordinates only. The visitor's location is never
    // requested here and never sent to any directions or routing service.
    const mapToggle = field('map-toggle');
    const mapToggleLabel = mapToggle.querySelector('[data-map-toggle-label]');
    const directionsLink = field('directions-link');
    const mapStatus = field('map-status');
    const mapWrapper = document.getElementById('listing-details-map-wrapper');
    const mapContainer = document.getElementById('listing-details-map');
    const mapboxToken = mapContainer?.dataset.mapboxToken;

    let currentListing = null;
    let locationMap = null;
    let locationMarker = null;

    function setMapStatus(message) {
        mapStatus.hidden = !message;
        mapStatus.textContent = message;
    }

    function showPhoto() {
        mapWrapper.hidden = true;
        modal.querySelectorAll('[data-listing-details-photo]').forEach((el) => { el.hidden = false; });
        // Re-applies which of image/placeholder belongs to this listing —
        // the blanket unhide above would otherwise show both.
        applyPhotoVisibility(currentListing);
        mapToggleLabel.textContent = 'View on map';
        setMapStatus('');
    }

    function showLocation(listing) {
        if (!window.mapboxgl || !mapboxToken || !mapboxgl.supported()) {
            setMapStatus("Map can't be displayed right now.");
            return;
        }

        // Mapbox/GeoJSON order is [longitude, latitude].
        const destination = [listing.lng, listing.lat];

        modal.querySelectorAll('[data-listing-details-photo]').forEach((el) => { el.hidden = true; });
        mapWrapper.hidden = false;
        mapToggleLabel.textContent = 'Show photo';

        if (!locationMap) {
            try {
                mapboxgl.accessToken = mapboxToken;
                locationMap = new mapboxgl.Map({
                    container: mapContainer,
                    style: MAP_STYLES.satellite,
                    center: destination,
                    zoom: 13,
                });
            } catch (error) {
                console.error('[listing-details-map] mapboxgl.Map() threw:', error);
                locationMap = null;
                showPhoto();
                setMapStatus("Map can't be displayed right now.");
                return;
            }

            locationMap.addControl(new MapStyleToggleControl(), 'top-left');
            locationMap.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');
            locationMap.on('error', (event) => console.error('[listing-details-map] map error:', event?.error ?? event));
        } else {
            locationMap.resize();
            locationMap.jumpTo({ center: destination, zoom: 13 });
        }

        locationMarker?.remove();
        locationMarker = new mapboxgl.Marker({ color: MAP_MARKER_COLORS.destination })
            .setLngLat(destination)
            .setPopup(new mapboxgl.Popup({ offset: 24 }).setText(listing.name))
            .addTo(locationMap);
    }

    mapToggle.addEventListener('click', () => {
        if (!currentListing) return;

        if (mapWrapper.hidden) {
            showLocation(currentListing);
        } else {
            showPhoto();
        }
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-listing-details]');
        if (!trigger) return;

        const listing = listings[trigger.dataset.listingDetails];
        if (listing) open(listing);
    });

    modal.querySelectorAll('[data-listing-details-close]').forEach((button) => {
        button.addEventListener('click', () => modal.close());
    });

    // A click landing on the <dialog> element itself (not its content) is a
    // click on the backdrop.
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
    });

    modal.addEventListener('close', () => document.body.classList.remove('overflow-hidden'));
}

/**
 * Landing page Signature Experiences "Explore all": reveals every other
 * Active listing (rendered hidden server-side) in place instead of leaving
 * the page, and toggles back to the curated six with "Show less". Without
 * JS the link still goes to /explore.
 */
function initExperiencesExploreAll() {
    const toggle = document.getElementById('experiences-explore-all');
    const extraCards = Array.from(document.querySelectorAll('[data-more-experience]'));

    if (!toggle || extraCards.length === 0) {
        return;
    }

    const label = toggle.querySelector('[data-action-label]');
    const icon = toggle.querySelector('[data-action-icon]');
    let expanded = false;

    toggle.setAttribute('role', 'button');
    toggle.setAttribute('aria-expanded', 'false');

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        expanded = !expanded;

        extraCards.forEach((card) => {
            card.hidden = !expanded;
        });

        toggle.setAttribute('aria-expanded', String(expanded));
        label.textContent = expanded ? 'Show less' : 'Explore all';
        icon.classList.toggle('ti-arrow-right', !expanded);
        icon.classList.toggle('ti-arrow-up', expanded);

        if (expanded) {
            extraCards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            document.getElementById('destinations').scrollIntoView({ behavior: 'smooth' });
        }
    });
}
