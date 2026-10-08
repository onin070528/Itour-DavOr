import { registerSW } from 'virtual:pwa-register';
import { initFindNearMe } from './find_near_me';

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
    initNavScrollSpy();
    initHeroCarousel();
});

/**
 * Highlights "Home"/"Nearby"/"Reviews" in the topbar as the corresponding
 * same-page section scrolls into view — the landing page's only nav items
 * without their own route ("Explore"/"Hotlines" keep their server-rendered,
 * route-matched active state untouched; this never runs on pages that lack
 * the #near-you/#reviews sections, i.e. everywhere but the landing page).
 */
function initNavScrollSpy() {
    const spyLabels = ['Home', 'Nearby', 'Reviews'];
    const navLinks = Array.from(document.querySelectorAll('[data-nav-link]'))
        .filter((link) => spyLabels.includes(link.dataset.navLink));

    if (!navLinks.length) return;

    const sections = ['Nearby', 'Reviews']
        .map((label) => ({ label, el: document.getElementById({ Nearby: 'near-you', Reviews: 'reviews' }[label]) }))
        .filter((s) => s.el);

    if (!sections.length) return;

    function paint(activeLabel) {
        navLinks.forEach((link) => {
            const active = link.dataset.navLink === activeLabel;

            if (link.dataset.navVariant === 'underline') {
                link.classList.toggle('border-accent-500', active);
                link.classList.toggle('text-primary-700', active);
                link.classList.toggle('font-semibold', active);
                link.classList.toggle('border-transparent', !active);
                link.classList.toggle('text-sand-900', !active);
            } else {
                link.classList.toggle('bg-sand-100', active);
                link.classList.toggle('text-primary-700', active);
                link.classList.toggle('font-semibold', active);
            }
        });
    }

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .map((entry) => sections.find((s) => s.el === entry.target)?.label)
            .filter(Boolean);

        if (visible.length) {
            // Lowest section currently in view wins when two overlap.
            paint(visible[visible.length - 1]);
            return;
        }

        // Nothing observed is intersecting: above the first section means
        // Home is still the active "section"; below it (past the last
        // section's bottom) leaves About as the closest match, since
        // there's nothing further down the page to hand off to.
        const aboveEverything = sections[0].el.getBoundingClientRect().top > 0;
        paint(aboveEverything ? 'Home' : 'About');
    }, { rootMargin: '-100px 0px -55% 0px', threshold: 0 });

    sections.forEach((s) => observer.observe(s.el));
}

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
 * Landing page "Find Places Near You" section (resources/views/components/
 * near-you-section.blade.php): a Mapbox map of the province's public places
 * that also plots Find Near Me results. Which places are nearest, and how
 * far they are, comes only from the server (Find Near Me ->
 * NearbySearchService); this map calculates nothing. The visitor's
 * approximate position (rounded, display only) arrives in the
 * "find-near-me:results" event from resources/js/find_near_me.js.
 */
function initNearbyMap() {
    const container = document.getElementById('nearby-map');
    const dataEl = document.getElementById('nearby-map-data');
    const statusText = document.getElementById('nearby-map-status-text');

    if (!container || !dataEl) {
        return;
    }

    const token = container.dataset.mapboxToken;
    if (!window.mapboxgl || !token) {
        if (statusText) statusText.textContent = "Map can't be displayed right now";
        console.error('[nearby-map] mapbox-gl failed to load, or no public Mapbox token was configured.');
        return;
    }

    if (!mapboxgl.supported()) {
        if (statusText) statusText.textContent = "Map can't be displayed in this browser";
        console.error('[nearby-map] mapboxgl.supported() returned false — no WebGL in this browser.');
        return;
    }

    const places = JSON.parse(dataEl.textContent);
    const centerLat = Number(container.dataset.mapboxCenterLat);
    const centerLng = Number(container.dataset.mapboxCenterLng);

    mapboxgl.accessToken = token;

    let map;
    try {
        map = new mapboxgl.Map({
            container,
            style: MAP_STYLES.satellite,
            center: [centerLng, centerLat],
            zoom: 8.4,
        });
        map.addControl(new MapStyleToggleControl(), 'top-left');
    } catch (error) {
        if (statusText) statusText.textContent = "Map can't be displayed right now";
        console.error('[nearby-map] mapboxgl.Map() threw:', error);
        return;
    }

    map.on('error', (event) => {
        console.error('[nearby-map] map error:', event?.error ?? event);
    });

    map.on('load', () => {
        map.resize();
    });

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

    // Mapbox/GeoJSON order is [longitude, latitude].
    places.forEach((place) => {
        new mapboxgl.Marker({ color: '#125d5a' })
            .setLngLat([place.lng, place.lat])
            .setPopup(new mapboxgl.Popup({ offset: 24 }).setHTML(`
                <p class="font-semibold text-sand-900">${escapeHtml(place.name)}</p>
                <p class="text-xs text-sand-600">${escapeHtml(place.categoryLabel)} · ${escapeHtml(place.barangay)}, ${escapeHtml(place.municipality)}</p>
            `))
            .addTo(map);
    });

    let resultMarkers = [];

    function clearResultMarkers() {
        resultMarkers.forEach((marker) => marker.remove());
        resultMarkers = [];
    }

    // Summary comment: Find Near Me results — the server's places, plus the
    // visitor's approximate position, which stays in this browser.
    document.addEventListener('find-near-me:results', (event) => {
        const { places: nearbyPlaces, origin } = event.detail;

        clearResultMarkers();
        resultMarkers.push(addPlaceMarker(
            map,
            { name: 'You are here (approximate)', lat: origin.lat, lng: origin.lng },
            MAP_MARKER_COLORS.reference,
            '<p class="font-semibold text-sand-900">You are here (approximate)</p>',
            { scale: 1.1, label: 'Your approximate location' },
        ));

        nearbyPlaces.forEach((place) => {
            resultMarkers.push(addPlaceMarker(
                map,
                { name: place.name, lat: place.latitude, lng: place.longitude },
                MAP_MARKER_COLORS[place.type] ?? MAP_MARKER_COLORS.establishment,
                `
                    <p class="font-semibold text-sand-900">${escapeHtml(place.name)}</p>
                    <p class="text-xs text-sand-600">${escapeHtml(place.subtype || place.category)} · ${escapeHtml(place.distanceLabel)}</p>
                    <a href="${escapeHtml(place.url)}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">View Details<i class="ti ti-arrow-right"></i></a>
                `,
                { label: `${place.name} — ${place.distanceLabel}` },
            ));
        });

        const bounds = new mapboxgl.LngLatBounds([origin.lng, origin.lat], [origin.lng, origin.lat]);
        nearbyPlaces.forEach((place) => bounds.extend([place.longitude, place.latitude]));
        map.fitBounds(bounds, { padding: 50, maxZoom: 14, duration: 0 });

        if (statusText) {
            statusText.textContent = nearbyPlaces.length > 0
                ? `Showing ${nearbyPlaces.length} ${nearbyPlaces.length === 1 ? 'place' : 'places'} near you`
                : 'No places found within that distance';
        }
    });

    document.addEventListener('find-near-me:cleared', () => {
        clearResultMarkers();

        if (statusText) {
            statusText.textContent = 'Showing destinations & establishments across Davao Oriental';
        }
    });
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
