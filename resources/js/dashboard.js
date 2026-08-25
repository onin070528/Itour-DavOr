/**
 * Shared frontend interactions for authenticated dashboards (PTO, LGU, and
 * later Establishment): sidebar submenus, filterable data tables, tabs,
 * modals, dropdowns, confirm dialogs, toasts, and a small SVG line chart
 * renderer. Everything here is generic and driven by data-* markers so
 * individual pages only need to add markup, not JS.
 */
document.addEventListener('DOMContentLoaded', () => {
    initSidebarSubmenus();
    initFilterableTables();
    initTabs();
    initModals();
    initEditTriggers();
    initDropdowns();
    initTrendCharts();
    initConfirmActions();
    initToastTriggers();
});

function initSidebarSubmenus() {
    document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
        const submenu = button.nextElementSibling;
        const chevron = button.querySelector('[data-nav-chevron]');
        if (!submenu) return;

        button.addEventListener('click', () => {
            const isOpen = submenu.classList.toggle('hidden') === false;
            button.setAttribute('aria-expanded', String(isOpen));
            chevron?.classList.toggle('rotate-180', isOpen);
        });
    });
}

/**
 * Generic filterable, paginated table: wrap a table (or card list) in
 * `[data-filterable-table]`, mark each row `[data-row]` with
 * `data-field-<key>="value"` attributes, and wire up controls with
 * `[data-filter-input]` (free-text, checked against every data-field-*) or
 * `[data-filter-select]` (exact match against `data-filter-key`).
 *
 * Optionally include `[data-result-count]` (text updated with the visible
 * count), `[data-empty-state]` (shown when nothing matches), and
 * `[data-page-size="10"]` on the root plus a `[data-pagination]` element to
 * paginate the filtered results.
 */
function initFilterableTables() {
    document.querySelectorAll('[data-filterable-table]').forEach((root) => {
        const rows = Array.from(root.querySelectorAll('[data-row]'));
        const textInputs = Array.from(root.querySelectorAll('[data-filter-input]'));
        const selects = Array.from(root.querySelectorAll('[data-filter-select]'));
        const countEl = root.querySelector('[data-result-count]');
        const emptyEl = root.querySelector('[data-empty-state]');
        const resetButton = root.querySelector('[data-filter-reset]');
        const paginationEl = root.querySelector('[data-pagination]');
        const pageSize = parseInt(root.dataset.pageSize, 10) || 0;
        let page = 1;

        function matches(row) {
            const query = textInputs.map((i) => i.value.trim().toLowerCase()).filter(Boolean);
            const activeSelects = selects
                .map((select) => ({ key: select.dataset.filterKey, value: select.value }))
                .filter((f) => f.value);

            const haystack = row.dataset.searchText ?? Object.values(row.dataset).join(' ').toLowerCase();
            const matchesText = query.every((q) => haystack.includes(q));
            const matchesSelects = activeSelects.every((f) => row.dataset[toCamel(f.key)] === f.value);

            return matchesText && matchesSelects;
        }

        function apply() {
            const matched = rows.filter(matches);
            const totalPages = pageSize ? Math.max(1, Math.ceil(matched.length / pageSize)) : 1;
            page = Math.min(page, totalPages);

            rows.forEach((row) => row.classList.add('hidden'));

            const pageRows = pageSize ? matched.slice((page - 1) * pageSize, page * pageSize) : matched;
            pageRows.forEach((row) => row.classList.remove('hidden'));

            if (countEl) countEl.textContent = String(matched.length);
            if (emptyEl) emptyEl.classList.toggle('hidden', matched.length !== 0);

            if (paginationEl) renderPagination(paginationEl, page, totalPages, (p) => { page = p; apply(); });
        }

        textInputs.forEach((input) => input.addEventListener('input', () => { page = 1; apply(); }));
        selects.forEach((select) => select.addEventListener('change', () => { page = 1; apply(); }));
        resetButton?.addEventListener('click', () => {
            textInputs.forEach((input) => (input.value = ''));
            selects.forEach((select) => (select.value = ''));
            page = 1;
            apply();
        });

        apply();
    });
}

function renderPagination(el, page, totalPages, onChange) {
    if (totalPages <= 1) {
        el.innerHTML = '';
        return;
    }

    const button = (label, target, disabled, active) => `
        <button
            type="button"
            data-page="${target}"
            ${disabled ? 'disabled' : ''}
            class="rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors ${
                active ? 'bg-primary-700 text-white' : disabled ? 'text-sand-300' : 'text-sand-700 hover:bg-sand-100'
            }"
        >${label}</button>
    `;

    el.innerHTML = [
        button('Previous', page - 1, page === 1, false),
        ...Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => button(String(p), p, false, p === page)),
        button('Next', page + 1, page === totalPages, false),
    ].join('');

    el.querySelectorAll('[data-page]').forEach((btn) => {
        btn.addEventListener('click', () => onChange(parseInt(btn.dataset.page, 10)));
    });
}

function toCamel(key) {
    return key.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
}

/**
 * Tab group: `[data-tabs]` wraps `button[data-tab-target="panelId"]`
 * triggers and `[data-tab-panel="panelId"]` panels.
 */
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((group) => {
        const buttons = Array.from(group.querySelectorAll('[data-tab-target]'));
        const panelHost = group.dataset.tabPanelHost
            ? document.querySelector(group.dataset.tabPanelHost)
            : document;

        function activate(target) {
            buttons.forEach((btn) => {
                const active = btn.dataset.tabTarget === target;
                btn.setAttribute('aria-selected', String(active));
                btn.classList.toggle('border-primary-700', active);
                btn.classList.toggle('text-primary-700', active);
                btn.classList.toggle('border-transparent', !active);
                btn.classList.toggle('text-sand-500', !active);
            });
            panelHost.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== target);
            });
        }

        buttons.forEach((btn) => btn.addEventListener('click', () => activate(btn.dataset.tabTarget)));
    });
}

/**
 * Modal: `[data-modal-open="id"]` opens `#id`, `[data-modal-close]` inside
 * it (or the `[data-modal-backdrop]`) closes it, Escape closes the topmost
 * open modal.
 */
function initModals() {
    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            document.getElementById(trigger.dataset.modalOpen)?.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((closer) => {
        closer.addEventListener('click', () => {
            closeModal(closer.closest('[data-modal]'));
            if (closer.dataset.toastMessage) {
                showToast(closer.dataset.toastMessage, closer.dataset.toastTone ?? 'success');
            }
        });
    });

    document.querySelectorAll('[data-modal-backdrop]').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal(backdrop.closest('[data-modal]'));
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = Array.from(document.querySelectorAll('[data-modal]:not(.hidden)')).pop();
        if (open) closeModal(open);
    });
}

function closeModal(modal) {
    if (!modal) return;
    modal.classList.add('hidden');
    if (!document.querySelector('[data-modal]:not(.hidden)')) {
        document.body.classList.remove('overflow-hidden');
    }
}

/**
 * Edit trigger: `[data-edit-trigger="modalId"]` with a `data-edit-values`
 * JSON blob copies those values into the named form fields inside that
 * modal, so one shared "Edit" modal can be reused for every row.
 */
function initEditTriggers() {
    document.querySelectorAll('[data-edit-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.editTrigger);
            const form = modal?.querySelector('form');
            if (!form) return;

            const values = JSON.parse(trigger.dataset.editValues || '{}');
            Object.entries(values).forEach(([key, value]) => {
                const field = form.elements.namedItem(key);
                if (field) field.value = value;
            });
        });
    });
}

/**
 * Confirmation dialog for destructive/state-changing actions (archive,
 * enable/disable): `[data-confirm-trigger]` with `data-confirm-title`,
 * `data-confirm-message`, `data-confirm-label`, and `data-confirm-tone`
 * ("danger" | "success") opens `#confirm-modal`, and confirming shows a
 * success toast — there is no backend behind this yet.
 */
function initConfirmActions() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;

    const titleEl = modal.querySelector('[data-confirm-title]');
    const messageEl = modal.querySelector('[data-confirm-message]');
    const confirmButton = modal.querySelector('[data-confirm-button]');

    document.querySelectorAll('[data-confirm-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            titleEl.textContent = trigger.dataset.confirmTitle ?? 'Are you sure?';
            messageEl.textContent = trigger.dataset.confirmMessage ?? 'This action cannot be undone.';
            confirmButton.textContent = trigger.dataset.confirmLabel ?? 'Confirm';
            confirmButton.dataset.confirmTone = trigger.dataset.confirmTone ?? 'danger';
            confirmButton.classList.toggle('bg-danger', confirmButton.dataset.confirmTone === 'danger');
            confirmButton.classList.toggle('bg-primary-700', confirmButton.dataset.confirmTone !== 'danger');

            confirmButton.onclick = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                showToast(trigger.dataset.confirmSuccess ?? 'Done.', 'success');
            };

            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    });
}

/**
 * Standalone toast trigger, for actions that aren't inside a modal:
 * `[data-toast-trigger]` with `data-toast-message` / `data-toast-tone`.
 */
function initToastTriggers() {
    document.querySelectorAll('[data-toast-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            showToast(trigger.dataset.toastMessage ?? 'Done.', trigger.dataset.toastTone ?? 'success');
        });
    });
}

/**
 * Success/error toast, auto-dismissed after a few seconds.
 */
function showToast(message, tone = 'success') {
    let root = document.getElementById('toast-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'toast-root';
        root.className = 'fixed bottom-5 right-5 z-[60] flex flex-col gap-2';
        document.body.appendChild(root);
    }

    const toast = document.createElement('div');
    const toneClasses = tone === 'danger'
        ? 'bg-danger-bg text-danger border-danger/20'
        : 'bg-success-bg text-success border-success/20';
    toast.className = `flex items-center gap-2 rounded-sm border px-4 py-2.5 text-sm font-semibold shadow-md ${toneClasses}`;
    toast.innerHTML = `<i class="ti ${tone === 'danger' ? 'ti-alert-triangle' : 'ti-circle-check'}"></i>${message}`;
    root.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Dropdown: `[data-dropdown-toggle]` toggles the nearest following
 * `[data-dropdown-menu]`. Closes on outside click or Escape.
 */
function initDropdowns() {
    document.querySelectorAll('[data-dropdown-toggle]').forEach((toggle) => {
        const menu = toggle.parentElement?.querySelector('[data-dropdown-menu]');
        if (!menu) return;

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const willOpen = menu.classList.contains('hidden');
            document.querySelectorAll('[data-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
            menu.classList.toggle('hidden', !willOpen);
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
    });
}

/**
 * Renders a lightweight SVG line chart into `[data-trend-chart]` elements
 * from an embedded JSON payload, with optional period-filter buttons
 * (`[data-trend-period]`) that swap the active series.
 */
function initTrendCharts() {
    document.querySelectorAll('[data-trend-chart]').forEach((container) => {
        const dataEl = document.getElementById(container.dataset.trendChart);
        if (!dataEl) return;

        const series = JSON.parse(dataEl.textContent);
        const svg = container.querySelector('svg');
        const labelsEl = container.querySelector('[data-trend-labels]');
        const periodButtons = document.querySelectorAll(`[data-trend-period][data-trend-target="${container.id}"]`);

        function render(period) {
            const points = series[period] ?? Object.values(series)[0];
            drawLineChart(svg, points.map((p) => p.value));
            if (labelsEl) {
                labelsEl.innerHTML = points.map((p) => `<span>${p.label}</span>`).join('');
            }
        }

        periodButtons.forEach((button) => {
            button.addEventListener('click', () => {
                periodButtons.forEach((b) => {
                    b.classList.remove('bg-sand-0', 'shadow-sm', 'text-primary-700');
                    b.classList.add('text-sand-600');
                });
                button.classList.add('bg-sand-0', 'shadow-sm', 'text-primary-700');
                button.classList.remove('text-sand-600');
                render(button.dataset.trendPeriod);
            });
        });

        const defaultButton = container.id
            ? document.querySelector(`[data-trend-period][data-trend-target="${container.id}"].bg-sand-0`)
            : null;
        render(defaultButton?.dataset.trendPeriod ?? Object.keys(series)[0]);
    });
}

function drawLineChart(svg, values, padding = 8) {
    if (!svg || !values.length) return;

    const width = 460;
    const height = 140;
    const max = Math.max(...values);
    const min = Math.min(...values, 0);
    const range = max - min || 1;

    const points = values.map((value, i) => {
        const x = padding + (i / (values.length - 1 || 1)) * (width - padding * 2);
        const y = height - padding - ((value - min) / range) * (height - padding * 2);
        return [x, y];
    });

    const linePath = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
    const areaPath = `${linePath} L${points[points.length - 1][0].toFixed(1)},${height} L${points[0][0].toFixed(1)},${height} Z`;

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.innerHTML = `
        <path d="${areaPath}" fill="var(--color-primary-100)" stroke="none"></path>
        <path d="${linePath}" fill="none" stroke="var(--color-primary-700)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
        ${points.map(([x, y]) => `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="2.5" fill="var(--color-primary-700)"></circle>`).join('')}
    `;
}
