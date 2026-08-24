document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPasswordToggle();
    initExplorePage();
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
