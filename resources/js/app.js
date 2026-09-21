document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPasswordToggle();
    initExplorePage();
    initNearbyMap();
    initChatbot();
    initEstablishmentQrForm();
});

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
    chipButtons.forEach((chip) => {
        const active = state.categories.has(chip.dataset.categoryChip);
        chip.setAttribute('aria-pressed', String(active));
        chip.classList.toggle('bg-primary-100', active);
        chip.classList.toggle('border-primary-300', active);
        chip.classList.toggle('text-primary-700', active);
    });
    setActiveView(state.view);

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

    chipButtons.forEach((chip) => {
        chip.addEventListener('click', () => {
            const slug = chip.dataset.categoryChip;
            const active = state.categories.has(slug);

            active ? state.categories.delete(slug) : state.categories.add(slug);
            chip.setAttribute('aria-pressed', String(!active));
            chip.classList.toggle('bg-primary-100', !active);
            chip.classList.toggle('border-primary-300', !active);
            chip.classList.toggle('text-primary-700', !active);

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
        chipButtons.forEach((chip) => {
            chip.setAttribute('aria-pressed', 'false');
            chip.classList.remove('bg-primary-100', 'border-primary-300', 'text-primary-700');
        });

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
                        <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800"><i class="ti ti-star text-accent-500"></i>${item.rating.toFixed(1)}</span>
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
                <td class="px-4 py-3 whitespace-nowrap text-sand-700"><i class="ti ti-star text-accent-500"></i> ${item.rating.toFixed(1)}</td>
                <td class="px-4 py-3 text-right"><a href="${item.href}" class="inline-flex items-center rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300 hover:text-primary-700">View</a></td>
            </tr>
        `).join('');
    }

    function renderMap(items) {
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
            style: 'mapbox://styles/mapbox/light-v11',
            center: [centerLng, centerLat],
            zoom: 8.4,
        });
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
    const iconOpen = document.getElementById('chatbot-toggle-icon-open');
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
        toggle.setAttribute('aria-label', open ? 'Close chat assistant' : 'Open chat assistant');
        iconOpen?.classList.toggle('hidden', open);
        iconClose?.classList.toggle('hidden', !open);
        if (open) input.focus();
    };

    toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
    closeButton?.addEventListener('click', () => setOpen(false));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        appendMessage(text, 'user');
        input.value = '';

        window.setTimeout(() => {
            appendMessage(
                "Thanks for your message! The AI assistant is still being set up — in the meantime, try Explore to browse destinations, or check the Emergency contacts in the footer.",
                'bot'
            );
        }, 500);
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
            wrapper.innerHTML = '<span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700"><i class="ti ti-message-chatbot text-sm" aria-hidden="true"></i></span>';
            bubble.className = 'max-w-[85%] rounded-md rounded-tl-none bg-sand-100 px-3 py-2 text-sm leading-relaxed text-sand-800';
            wrapper.appendChild(bubble);
        }

        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    }
}

/**
 * Establishment QR self-registration form (resources/views/lgu/establishmentQR.blade.php).
 * Wires up every +/- counter, keeps the "Total Party Size" readout in sync,
 * and swaps in a success step on submit.
 *
 * The total counts the visitor (1) plus companions — companions are read
 * from the "By Gender" counters only, since every companion has exactly
 * one gender, so summing that group (rather than also adding the Age
 * Group / Tourist Type breakdowns) avoids counting the same person twice.
 */
function initEstablishmentQrForm() {
    const form = document.getElementById('establishment-qr-form');
    if (!form) return;

    const counters = Array.from(form.querySelectorAll('[data-counter]'));
    const genderCounters = counters.filter((el) => ['male', 'female'].includes(el.dataset.counter));
    const totalValue = document.getElementById('qr-total-value');
    const totalCaption = document.getElementById('qr-total-caption');
    const formStep = document.getElementById('qr-form-step');
    const successStep = document.getElementById('qr-success-step');
    const resetButton = document.getElementById('qr-form-reset');

    const readValue = (counter) => Number(counter.querySelector('[data-counter-value]').textContent) || 0;
    const writeValue = (counter, value) => {
        counter.querySelector('[data-counter-value]').textContent = String(Math.max(0, value));
    };

    function updateTotal() {
        const companions = genderCounters.reduce((sum, counter) => sum + readValue(counter), 0);
        totalValue.textContent = String(1 + companions);
        totalCaption.innerHTML = `You + <b>${companions}</b> companion${companions === 1 ? '' : 's'}`;
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
            // so they're read directly rather than via FormData.
            const payload = {
                visitorName: form.elements.namedItem('visitorName')?.value,
                visitorContact: form.elements.namedItem('visitorContact')?.value,
                ...Object.fromEntries(counters.map((counter) => [counter.dataset.counter, readValue(counter)])),
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
