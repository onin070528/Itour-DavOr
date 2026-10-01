document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPasswordToggle();
    initLoginForm();
    initExplorePage();
    initNearbyMap();
    initListingDetailsModal();
    initExperiencesExploreAll();
    initChatbot();
    initEstablishmentQrForm();
    initNavScrollSpy();
    initHeroCarousel();
});

/**
 * Highlights "Home"/"Nearby"/"Reviews"/"About" in the topbar as the
 * corresponding same-page section scrolls into view — the landing page's
 * only nav items without their own route ("Explore" keeps its
 * server-rendered, route-matched active state untouched; this never runs
 * on pages that lack the #near-you/#reviews/#about sections, i.e.
 * everywhere but the landing page).
 */
function initNavScrollSpy() {
    const spyLabels = ['Home', 'Nearby', 'Reviews', 'About'];
    const navLinks = Array.from(document.querySelectorAll('[data-nav-link]'))
        .filter((link) => spyLabels.includes(link.dataset.navLink));

    if (!navLinks.length) return;

    const sections = ['Nearby', 'Reviews', 'About']
        .map((label) => ({ label, el: document.getElementById({ Nearby: 'near-you', Reviews: 'reviews', About: 'about' }[label]) }))
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
 * The consolidated /explore hub: Grid, Table, and Map views sharing one
 * filter state (search text, municipality, categories), all driven from a
 * single JSON payload embedded in the page — no full page reload on filter
 * or view changes.
 */
function initExplorePage() {
    const root = document.getElementById('explore-root');
    const dataEl = document.getElementById('explore-data');

    if (!root || !dataEl) {
        return;
    }

    const { listings, categories, municipalities } = JSON.parse(dataEl.textContent);

    const categoryLabel = (slug) => categories.find((c) => c.slug === slug)?.label ?? slug;
    const municipalityPosition = (name) => municipalities.find((m) => m.name === name);

    const state = {
        q: '',
        municipality: '',
        categories: new Set(),
        view: 'grid',
    };

    // Prime the filter state from the URL (hero search, quick pills, and
    // the homepage's municipality chips all deep-link here).
    const params = new URLSearchParams(window.location.search);
    if (params.get('q')) state.q = params.get('q');
    if (params.get('municipality')) state.municipality = params.get('municipality');
    if (params.get('category')) state.categories.add(params.get('category'));

    const searchInput = document.getElementById('explore-search');
    const municipalitySelect = document.getElementById('explore-municipality');
    // Multiple "All"/category elements can exist at once now (the desktop
    // sidebar list and the mobile horizontally-scrollable chips render the
    // same slugs twice) — querySelectorAll + the shared syncChipStates()
    // below keeps every copy of a given control in sync.
    const allChips = Array.from(document.querySelectorAll('[data-category-all]'));
    const chipButtons = Array.from(document.querySelectorAll('[data-category-chip]'));
    const viewButtons = Array.from(document.querySelectorAll('[data-view-option]'));
    const countEl = document.getElementById('explore-count');
    const emptyEl = document.getElementById('explore-empty');
    const resetButton = document.getElementById('explore-reset');
    const views = {
        grid: document.getElementById('explore-grid'),
        table: document.getElementById('explore-table'),
        map: document.getElementById('explore-map'),
    };
    const tableBody = document.getElementById('explore-table-body');
    const mapCanvas = document.getElementById('explore-map-canvas');

    // --- Sync controls to the initial state -------------------------------
    searchInput.value = state.q;
    municipalitySelect.value = state.municipality;
    syncChipStates();
    setActiveView(state.view);

    // "All" and the per-category chips share one active-state paint, so
    // there's exactly one place that decides which chip looks selected —
    // "All" reads as active whenever no category chip is (an empty Set),
    // never as a chip of its own kind.
    function syncChipStates() {
        const noneActive = state.categories.size === 0;

        allChips.forEach((chip) => {
            chip.setAttribute('aria-pressed', String(noneActive));
            chip.classList.toggle('bg-primary-100', noneActive);
            chip.classList.toggle('border-primary-300', noneActive);
            chip.classList.toggle('text-primary-700', noneActive);
        });

        chipButtons.forEach((chip) => {
            const active = state.categories.has(chip.dataset.categoryChip);
            chip.setAttribute('aria-pressed', String(active));
            chip.classList.toggle('bg-primary-100', active);
            chip.classList.toggle('border-primary-300', active);
            chip.classList.toggle('text-primary-700', active);
        });
    }

    // --- Wire up controls ---------------------------------------------------
    let searchDebounce;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            state.q = searchInput.value.trim();
            render();
        }, 150);
    });

    municipalitySelect.addEventListener('change', () => {
        state.municipality = municipalitySelect.value;
        render();
    });

    allChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            state.categories.clear();
            syncChipStates();
            render();
        });
    });

    chipButtons.forEach((chip) => {
        chip.addEventListener('click', () => {
            const slug = chip.dataset.categoryChip;
            const active = state.categories.has(slug);

            active ? state.categories.delete(slug) : state.categories.add(slug);
            syncChipStates();
            render();
        });
    });

    viewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.view = button.dataset.viewOption;
            setActiveView(state.view);
            render();
        });
    });

    resetButton?.addEventListener('click', () => {
        state.q = '';
        state.municipality = '';
        state.categories.clear();

        searchInput.value = '';
        municipalitySelect.value = '';
        syncChipStates();

        render();
    });

    function setActiveView(view) {
        viewButtons.forEach((button) => {
            const active = button.dataset.viewOption === view;
            button.setAttribute('aria-pressed', String(active));
            button.classList.toggle('bg-sand-0', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('text-primary-700', active);
            button.classList.toggle('text-sand-600', !active);
        });

        Object.entries(views).forEach(([name, el]) => {
            el.classList.toggle('hidden', name !== view || filtered().length === 0);
        });
    }

    function filtered() {
        const q = state.q.toLowerCase();

        return listings.filter((item) => {
            if (q) {
                const haystack = `${item.name} ${item.description} ${item.municipality} ${item.barangay}`.toLowerCase();
                if (!haystack.includes(q)) return false;
            }

            if (state.municipality && item.municipality !== state.municipality) return false;
            if (state.categories.size && !state.categories.has(item.category)) return false;

            return true;
        });
    }

    function render() {
        const items = filtered();

        countEl.textContent = `${items.length} verified listing${items.length === 1 ? '' : 's'} from the Provincial Tourism Office and the 11 municipal tourism offices.`;
        emptyEl.classList.toggle('hidden', items.length !== 0);
        emptyEl.classList.toggle('flex', items.length === 0);

        Object.entries(views).forEach(([name, el]) => {
            el.classList.toggle('hidden', name !== state.view || items.length === 0);
        });

        if (items.length === 0) return;

        if (state.view === 'grid') renderGrid(items);
        if (state.view === 'table') renderTable(items);
        if (state.view === 'map') renderMap(items);
    }

    function renderGrid(items) {
        views.grid.innerHTML = items.map((item) => `
            <article class="group flex flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="relative h-44 overflow-hidden bg-sand-200">
                    <img src="/storage/itour-images/${item.image}" alt="${item.name}" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/55 via-transparent to-transparent"></div>
                    <span class="relative m-3 inline-block rounded-sm bg-sand-900/45 px-2.5 py-1 text-xs font-semibold text-sand-0">${categoryLabel(item.category)}</span>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-display text-lg font-bold text-sand-900">${item.name}</h3>
                        <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800"><i class="ti ti-star text-accent-500"></i>${item.rating !== null ? item.rating.toFixed(1) : 'New'}</span>
                    </div>
                    <p class="flex items-center gap-1 text-xs font-medium text-sand-500"><i class="ti ti-map-pin"></i>${item.barangay}, ${item.municipality}</p>
                    <p class="text-sm leading-relaxed text-sand-600">${item.description}</p>
                    <a href="${item.href}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">View Details<i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5"></i></a>
                </div>
            </article>
        `).join('');
    }

    function renderTable(items) {
        tableBody.innerHTML = items.map((item) => `
            <tr class="hover:bg-sand-50">
                <td class="flex items-center gap-3 px-4 py-3">
                    <span class="h-11 w-11 shrink-0 overflow-hidden rounded-sm bg-sand-200"><img src="/storage/itour-images/${item.image}" alt="" class="h-full w-full object-cover"></span>
                    <span class="font-semibold text-sand-900">${item.name}</span>
                </td>
                <td class="px-4 py-3 text-sand-700">${categoryLabel(item.category)}</td>
                <td class="px-4 py-3 text-sand-700">${item.barangay}, ${item.municipality}</td>
                <td class="px-4 py-3 text-sand-700">${item.contactOffice}<br><span class="text-xs text-sand-500">${item.contactPhone}</span></td>
                <td class="px-4 py-3 text-sand-700">${item.hours}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sand-700"><i class="ti ti-star text-accent-500"></i> ${item.rating !== null ? item.rating.toFixed(1) : 'New'}</td>
                <td class="px-4 py-3 text-right"><a href="${item.href}" class="inline-flex items-center rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300 hover:text-primary-700">View</a></td>
            </tr>
        `).join('');
    }

    // Mapbox is created lazily the first time the Map view is shown — a map
    // built inside a display:none container measures 0x0 and renders blank.
    const mapboxToken = mapCanvas.dataset.mapboxToken;
    const canUseMapbox = Boolean(window.mapboxgl && mapboxToken && mapboxgl.supported());
    let mapboxMap = null;
    let mapboxMarkers = [];

    if (!canUseMapbox) {
        console.error('[explore-map] mapbox-gl unavailable, no token, or no WebGL — using the illustrative map.');
        document.getElementById('explore-map-caption').textContent =
            'Illustrative province map — not to scale. Pins mark the municipality of each filtered listing.';
    }

    function renderMap(items) {
        if (canUseMapbox) {
            renderMapboxMap(items);
        } else {
            renderIllustrativeMap(items);
        }
    }

    function renderMapboxMap(items) {
        if (!mapboxMap) {
            mapboxgl.accessToken = mapboxToken;
            mapboxMap = new mapboxgl.Map({
                container: mapCanvas,
                style: MAP_STYLES.satellite,
                center: [Number(mapCanvas.dataset.mapboxCenterLng), Number(mapCanvas.dataset.mapboxCenterLat)],
                zoom: 8.4,
            });
            mapboxMap.addControl(new MapStyleToggleControl(), 'top-left');
            mapboxMap.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
            mapboxMap.on('error', (event) => console.error('[explore-map] map error:', event?.error ?? event));
        } else {
            // The container may have just been un-hidden by a view switch.
            mapboxMap.resize();
        }

        mapboxMarkers.forEach((marker) => marker.remove());

        const plotted = items.filter((item) => item.lat !== null && item.lng !== null);

        mapboxMarkers = plotted.map((item) => new mapboxgl.Marker({ color: '#125d5a' })
            .setLngLat([item.lng, item.lat])
            .setPopup(new mapboxgl.Popup({ offset: 24, maxWidth: '260px' }).setHTML(`
                <img src="/storage/itour-images/${escapeHtml(item.image)}" alt="" class="mb-2 h-24 w-full rounded-sm object-cover">
                <p class="font-semibold text-sand-900">${escapeHtml(item.name)}</p>
                <p class="text-xs text-sand-600">${escapeHtml(categoryLabel(item.category))} · ${escapeHtml(item.barangay)}, ${escapeHtml(item.municipality)}</p>
                <a href="${escapeHtml(item.href)}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">View Details<i class="ti ti-arrow-right"></i></a>
            `))
            .addTo(mapboxMap));

        const unplotted = items.length - plotted.length;
        document.getElementById('explore-map-caption').textContent = unplotted > 0
            ? `Pins mark ${plotted.length} of ${items.length} filtered listings — ${unplotted} ${unplotted === 1 ? "doesn't" : "don't"} have coordinates yet.`
            : 'Pins mark every filtered listing — click one for details.';

        if (plotted.length === 1) {
            mapboxMap.flyTo({ center: [plotted[0].lng, plotted[0].lat], zoom: 13 });
        } else if (plotted.length > 1) {
            const bounds = new mapboxgl.LngLatBounds();
            plotted.forEach((item) => bounds.extend([item.lng, item.lat]));
            mapboxMap.fitBounds(bounds, { padding: 60, maxZoom: 13 });
        }
    }

    function renderIllustrativeMap(items) {
        const municipalityLabels = municipalities.map((m) => `
            <div class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center gap-1 text-[10px] font-medium text-sand-500" style="top:${m.top}%; left:${m.left}%;">
                <span class="h-1.5 w-1.5 rounded-full bg-sand-400"></span>${m.name}
            </div>
        `).join('');

        const seenPerMunicipality = {};
        const pins = items.map((item) => {
            const pos = municipalityPosition(item.municipality);
            if (!pos) return '';

            const n = seenPerMunicipality[item.municipality] ?? 0;
            seenPerMunicipality[item.municipality] = n + 1;
            const jitterTop = pos.top + (n % 3) * 2.2 - 2.2;
            const jitterLeft = pos.left + Math.floor(n / 3) * 2.5;

            return `
                <div class="absolute -translate-x-1/2 -translate-y-full text-primary-700 drop-shadow" style="top:${jitterTop}%; left:${jitterLeft}%;" title="${item.name} — ${categoryLabel(item.category)}">
                    <i class="ti ti-map-pin text-2xl"></i>
                </div>
            `;
        }).join('');

        mapCanvas.innerHTML = municipalityLabels + pins;
    }

    render();
}

/**
 * Landing page "Find Places Near You" section (resources/views/components/
 * near-you-section.blade.php): a live Mapbox GL map plotting every active,
 * geocoded destination/establishment, plus a geolocation-driven button that
 * flies to the visitor's position and highlights the nearest place.
 */
function initNearbyMap() {
    const container = document.getElementById('nearby-map');
    const dataEl = document.getElementById('nearby-map-data');
    const button = document.getElementById('find-near-you-button');
    const statusText = document.getElementById('nearby-map-status-text');

    if (!container || !dataEl) {
        return;
    }

    const token = container.dataset.mapboxToken;
    if (!window.mapboxgl || !token) {
        if (statusText) statusText.textContent = 'Map is currently unavailable';
        console.error('[nearby-map] mapbox-gl failed to load, or no Mapbox token was configured.');
        return;
    }

    if (!mapboxgl.supported()) {
        if (statusText) statusText.textContent = "Your browser doesn't support this map (WebGL required)";
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
        if (statusText) statusText.textContent = 'Map failed to start — see console for details';
        console.error('[nearby-map] mapboxgl.Map() threw:', error);
        return;
    }

    map.on('error', (event) => {
        console.error('[nearby-map] map error:', event?.error ?? event);
        if (statusText) statusText.textContent = 'Map failed to load tiles — check your connection';
    });

    map.on('load', () => {
        map.resize();
    });

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

    const placeMarkers = places.map((place) => {
        const marker = new mapboxgl.Marker({ color: '#125d5a' })
            .setLngLat([place.lng, place.lat])
            .setPopup(new mapboxgl.Popup({ offset: 24 }).setHTML(`
                <p class="font-semibold text-sand-900">${escapeHtml(place.name)}</p>
                <p class="text-xs text-sand-600">${escapeHtml(place.categoryLabel)} · ${escapeHtml(place.barangay)}, ${escapeHtml(place.municipality)}</p>
            `))
            .addTo(map);

        return { place, marker };
    });

    let userMarker = null;

    button?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            if (statusText) statusText.textContent = "Your browser doesn't support geolocation";
            return;
        }

        button.disabled = true;
        const originalLabel = button.innerHTML;
        button.innerHTML = '<i class="ti ti-loader-2 animate-spin" aria-hidden="true"></i> Locating you…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;

                if (userMarker) userMarker.remove();
                userMarker = new mapboxgl.Marker({ color: '#cb6e30' })
                    .setLngLat([longitude, latitude])
                    .setPopup(new mapboxgl.Popup({ offset: 24 }).setText('You are here'))
                    .addTo(map);

                map.flyTo({ center: [longitude, latitude], zoom: 11 });

                if (placeMarkers.length) {
                    const nearest = placeMarkers.reduce((closest, entry) => {
                        const distance = haversineDistanceKm(latitude, longitude, entry.place.lat, entry.place.lng);
                        return !closest || distance < closest.distance ? { ...entry, distance } : closest;
                    }, null);

                    nearest.marker.togglePopup();
                    if (statusText) {
                        statusText.textContent = `Nearest to you: ${nearest.place.name} (${nearest.distance.toFixed(1)} km away)`;
                    }
                } else if (statusText) {
                    statusText.textContent = "You're on the map — no nearby listings to compare yet";
                }

                button.disabled = false;
                button.innerHTML = originalLabel;
            },
            () => {
                if (statusText) statusText.textContent = "Couldn't get your location — check your browser's location permission";
                button.disabled = false;
                button.innerHTML = originalLabel;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
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

function haversineDistanceKm(lat1, lon1, lat2, lon2) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const R = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
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
 * Wires up every +/- counter, keeps the "Total Registration Headcount" readout
 * in sync, and swaps in a success step on submit.
 *
 * Companion Headcount is a Foreign/Local x Male/Female x Adults/Children/Seniors
 * matrix — each cell's data-counter is "<group>-<gender>-<age>" (e.g.
 * "foreign-male-adults"). computeMatrixSums() parses that key on every cell to
 * roll the 12 granular counters back up into the 7 flat totals
 * (male/female/adults/children/seniors/local/foreign) CheckinController
 * already validates and stores — every companion is counted exactly once per
 * dimension, so foreign+local always equals male+female.
 */
function initEstablishmentQrForm() {
    const form = document.getElementById('establishment-qr-form');
    if (!form) return;

    const counters = Array.from(form.querySelectorAll('[data-counter]'));
    const totalValue = document.getElementById('qr-total-value');
    const foreignValue = document.getElementById('qr-foreign-value');
    const localValue = document.getElementById('qr-local-value');
    const formStep = document.getElementById('qr-form-step');
    const successStep = document.getElementById('qr-success-step');
    const resetButton = document.getElementById('qr-form-reset');

    const readValue = (counter) => Number(counter.querySelector('[data-counter-value]').textContent) || 0;
    const writeValue = (counter, value) => {
        counter.querySelector('[data-counter-value]').textContent = String(Math.max(0, value));
    };

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

    function updateTotal() {
        const sums = computeMatrixSums();
        const companions = sums.foreign + sums.local;
        totalValue.textContent = String(1 + companions);
        foreignValue.textContent = String(sums.foreign);
        localValue.textContent = String(sums.local);
    }

    counters.forEach((counter) => {
        counter.querySelector('[data-counter-decrement]').addEventListener('click', () => {
            writeValue(counter, readValue(counter) - 1);
            updateTotal();
        });
        counter.querySelector('[data-counter-increment]').addEventListener('click', () => {
            writeValue(counter, readValue(counter) + 1);
            updateTotal();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            // Companion counts live in [data-counter-value] spans, not real
            // form fields (see resources/views/components/lgu/qr-counter.blade.php),
            // so they're read directly rather than via FormData, then rolled
            // up from the 12-cell matrix into the 7 flat fields the backend
            // validates (see computeMatrixSums() above).
            const payload = {
                visitorName: form.elements.namedItem('visitorName')?.value,
                visitorContact: form.elements.namedItem('visitorContact')?.value,
                ...computeMatrixSums(),
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
                throw new Error('Request failed');
            }

            formStep?.classList.add('hidden');
            successStep?.classList.remove('hidden');
            successStep?.classList.add('flex');
        } catch {
            alert("Couldn't submit your registration — please check your connection and try again.");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });

    resetButton?.addEventListener('click', () => {
        form.reset();
        counters.forEach((counter) => writeValue(counter, 0));
        updateTotal();
        successStep?.classList.add('hidden');
        successStep?.classList.remove('flex');
        formStep?.classList.remove('hidden');
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

    function open(listing) {
        field('image').src = `/storage/itour-images/${listing.image}`;
        field('image').alt = `${listing.name}, ${listing.municipality}`;
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
        field('directions').hidden = listing.lat === null || listing.lng === null;
        showPhoto();

        modal.showModal();
        document.body.classList.add('overflow-hidden');
    }

    // "Get directions": swaps the hero photo for a Mapbox map, then routes
    // from the visitor's location (browser geolocation) to the listing with
    // the Mapbox Directions API. Without a location it shows the destination.
    const directionsButton = field('directions');
    const directionsLabel = directionsButton.querySelector('[data-directions-label]');
    const directionsStatus = field('directions-status');
    const mapWrapper = document.getElementById('listing-details-map-wrapper');
    const mapContainer = document.getElementById('listing-details-map');
    const mapboxToken = mapContainer?.dataset.mapboxToken;
    const emptyRoute = { type: 'FeatureCollection', features: [] };

    let currentListing = null;
    let directionsMap = null;
    let directionsMapReady = null;
    let directionsMarkers = [];
    let directionsRequestId = 0;
    let currentRoute = emptyRoute;

    function setRoute(data) {
        currentRoute = data;
        directionsMapReady?.then(() => directionsMap.getSource('route')?.setData(data));
    }

    function setDirectionsStatus(message, icon = 'ti-info-circle') {
        directionsStatus.hidden = !message;
        directionsStatus.innerHTML = message
            ? `<i class="ti ${icon}" aria-hidden="true"></i><span>${escapeHtml(message)}</span>`
            : '';
    }

    function showPhoto() {
        directionsRequestId++;
        mapWrapper.hidden = true;
        modal.querySelectorAll('[data-listing-details-photo]').forEach((el) => { el.hidden = false; });
        directionsLabel.textContent = 'Get directions';
        setDirectionsStatus('');
    }

    function showDirections(listing) {
        if (!window.mapboxgl || !mapboxToken || !mapboxgl.supported()) {
            setDirectionsStatus('The map is currently unavailable.', 'ti-alert-circle');
            return;
        }

        const requestId = ++directionsRequestId;
        const destination = [listing.lng, listing.lat];

        modal.querySelectorAll('[data-listing-details-photo]').forEach((el) => { el.hidden = true; });
        mapWrapper.hidden = false;
        directionsLabel.textContent = 'Show photo';

        if (!directionsMap) {
            mapboxgl.accessToken = mapboxToken;
            directionsMap = new mapboxgl.Map({
                container: mapContainer,
                style: MAP_STYLES.satellite,
                center: destination,
                zoom: 13,
            });
            directionsMap.addControl(new MapStyleToggleControl(), 'top-left');
            directionsMap.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');
            directionsMap.on('error', (event) => console.error('[directions-map] map error:', event?.error ?? event));

            // The route layer is dropped on every Satellite/Streets swap, so
            // it is (re-)added with the current route on each style load.
            directionsMapReady = new Promise((resolve) => directionsMap.on('style.load', () => {
                directionsMap.addSource('route', { type: 'geojson', data: currentRoute });
                directionsMap.addLayer({
                    id: 'route',
                    type: 'line',
                    source: 'route',
                    layout: { 'line-join': 'round', 'line-cap': 'round' },
                    paint: { 'line-color': '#1d9bf0', 'line-width': 6, 'line-opacity': 0.9 },
                });
                resolve();
            }));
        } else {
            directionsMap.resize();
            directionsMap.jumpTo({ center: destination, zoom: 13 });
        }

        directionsMarkers.forEach((marker) => marker.remove());
        directionsMarkers = [
            new mapboxgl.Marker({ color: '#125d5a' })
                .setLngLat(destination)
                .setPopup(new mapboxgl.Popup({ offset: 24 }).setText(listing.name))
                .addTo(directionsMap),
        ];
        setRoute(emptyRoute);

        if (!navigator.geolocation) {
            setDirectionsStatus("Your browser doesn't support location — showing the destination only.", 'ti-alert-circle');
            return;
        }

        setDirectionsStatus('Finding your location…', 'ti-loader-2 animate-spin');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (requestId !== directionsRequestId) return;
                routeFrom([position.coords.longitude, position.coords.latitude], destination, requestId);
            },
            () => {
                if (requestId !== directionsRequestId) return;
                setDirectionsStatus("Couldn't get your location — check your browser's location permission. Showing the destination only.", 'ti-alert-circle');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    async function routeFrom(origin, destination, requestId) {
        directionsMarkers.push(
            new mapboxgl.Marker({ color: '#cb6e30' })
                .setLngLat(origin)
                .setPopup(new mapboxgl.Popup({ offset: 24 }).setText('You are here'))
                .addTo(directionsMap)
        );
        setDirectionsStatus('Calculating route…', 'ti-loader-2 animate-spin');

        try {
            const coordinates = `${origin.join(',')};${destination.join(',')}`;
            const response = await fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/${coordinates}?geometries=geojson&overview=full&access_token=${encodeURIComponent(mapboxToken)}`);
            const data = await response.json();
            await directionsMapReady;

            if (requestId !== directionsRequestId) return;

            const route = data.routes?.[0];
            const bounds = new mapboxgl.LngLatBounds(origin, origin).extend(destination);

            if (!response.ok || !route) {
                directionsMap.fitBounds(bounds, { padding: 50, maxZoom: 14 });
                setDirectionsStatus('No driving route found from your location.', 'ti-alert-circle');
                return;
            }

            setRoute({ type: 'Feature', geometry: route.geometry });
            route.geometry.coordinates.forEach((point) => bounds.extend(point));
            directionsMap.fitBounds(bounds, { padding: 50, maxZoom: 15 });

            const km = (route.distance / 1000).toFixed(1);
            const minutes = Math.round(route.duration / 60);
            const duration = minutes >= 60 ? `${Math.floor(minutes / 60)} hr ${minutes % 60} min` : `${minutes} min`;
            setDirectionsStatus(`${km} km · about ${duration} by car`, 'ti-car');
        } catch (error) {
            if (requestId !== directionsRequestId) return;
            console.error('[directions-map] directions request failed:', error);
            setDirectionsStatus("Couldn't load the route — check your connection.", 'ti-alert-circle');
        }
    }

    directionsButton.addEventListener('click', () => {
        if (!currentListing) return;

        if (mapWrapper.hidden) {
            showDirections(currentListing);
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
