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
    initReportGenerator();
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
 * enable/disable, verify, remove): `[data-confirm-trigger]` with
 * `data-confirm-title`, `data-confirm-message`, `data-confirm-label`, and
 * `data-confirm-tone` ("danger" | "success") opens `#confirm-modal`.
 * Confirming shows a success toast and, if the trigger also carries
 * `data-confirm-remove-target="<selector>"`, removes the trigger's closest
 * matching ancestor from the DOM (used for e.g. "remove this photo").
 * There is no backend behind this yet.
 *
 * Delegated on `document` (rather than bound per-trigger) so it also works
 * for triggers added dynamically after page load, e.g. a newly uploaded
 * image card's Remove button.
 */
function initConfirmActions() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;

    const titleEl = modal.querySelector('[data-confirm-title]');
    const messageEl = modal.querySelector('[data-confirm-message]');
    const confirmButton = modal.querySelector('[data-confirm-button]');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-confirm-trigger]');
        if (!trigger) return;

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

            const removeTarget = trigger.dataset.confirmRemoveTarget;
            if (removeTarget) {
                trigger.closest(removeTarget)?.remove();
            }
        };

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
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

/**
 * Reports page: Report Generator → Preview → Recent Reports, shared by all
 * three roles via <x-dashboard.report-workspace> + <x-dashboard.report-history-table>.
 * There is no report-generation backend yet, so "generating" a report just
 * reveals the matching pre-rendered preview panel (built server-side from
 * real mock data — see each role's MockData::reportPreviewData()) after a
 * short simulated delay. Every preview panel already exists in the DOM;
 * this function only ever toggles visibility and fills in a few header
 * fields, it never builds report content from raw strings.
 */
function initReportGenerator() {
    const typeSelect = document.getElementById('report-type-select');
    const form = document.getElementById('report-generator-form');
    if (!typeSelect || !form) return;

    const fromInput = document.getElementById('report-from');
    const toInput = document.getElementById('report-to');
    const descriptionEl = document.getElementById('report-type-description');
    const filtersSection = document.getElementById('report-filters-section');
    const errorEl = document.getElementById('report-generator-error');
    const generateButton = document.getElementById('report-generate-button');
    const generateIcon = generateButton?.querySelector('[data-generate-icon]');
    const generateLabel = generateButton?.querySelector('[data-generate-label]');
    const successBanner = document.getElementById('report-success-banner');
    const previewSection = document.getElementById('report-preview');
    const historyBody = document.getElementById('report-history-body');
    const historyTable = document.querySelector('[data-report-history-table]');
    const historyEmpty = document.querySelector('[data-report-history-empty]');
    const currentUserName = document.querySelector('[data-preview-generated-by]')?.textContent ?? '';

    function updateFiltersForSelectedType() {
        const selected = typeSelect.selectedOptions[0];
        const activeFilters = (selected?.dataset.filters ?? '').split(',').filter(Boolean);

        document.querySelectorAll('[data-report-filter]').forEach((el) => {
            el.classList.toggle('hidden', !activeFilters.includes(el.dataset.reportFilter));
        });

        filtersSection?.classList.toggle('hidden', activeFilters.length === 0);

        if (descriptionEl) {
            descriptionEl.textContent = selected?.dataset.description ?? '';
        }
    }

    typeSelect.addEventListener('change', updateFiltersForSelectedType);
    updateFiltersForSelectedType();

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function clearError() {
        errorEl?.classList.add('hidden');
    }

    function formatInputDate(value) {
        if (!value) return '';
        const [y, m, d] = value.split('-').map(Number);

        return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function setLoading(isLoading) {
        if (!generateButton) return;
        generateButton.disabled = isLoading;
        generateIcon?.classList.toggle('ti-file-report', !isLoading);
        generateIcon?.classList.toggle('ti-loader-2', isLoading);
        generateIcon?.classList.toggle('animate-spin', isLoading);
        if (generateLabel) {
            generateLabel.textContent = isLoading ? 'Generating report…' : 'Generate Report';
        }
    }

    /**
     * Reveals the pre-rendered panel for `typeKey` and fills in the
     * letterhead fields. Returns false if that type has no panel (nothing
     * to show), so callers can bail out cleanly.
     */
    function renderPreview(typeKey, periodLabel, generatedAtLabel, generatedByLabel) {
        const panels = document.querySelectorAll('[data-preview-panel]');
        let matched = false;
        panels.forEach((panel) => {
            const isMatch = panel.dataset.previewPanel === typeKey;
            panel.classList.toggle('hidden', !isMatch);
            matched = matched || isMatch;
        });
        if (!matched || !previewSection) return false;

        const typeOption = typeSelect.querySelector(`option[value="${typeKey}"]`);
        const titleEl = document.querySelector('[data-preview-title]');
        const periodEl = document.querySelector('[data-preview-period]');
        const generatedDateEl = document.querySelector('[data-preview-generated-date]');
        const generatedByEl = document.querySelector('[data-preview-generated-by]');

        if (titleEl) titleEl.textContent = typeOption?.textContent ?? '';
        if (periodEl) periodEl.textContent = periodLabel;
        if (generatedDateEl) generatedDateEl.textContent = generatedAtLabel;
        if (generatedByEl) generatedByEl.textContent = generatedByLabel;

        // The trend chart's SVG path can't be pre-rendered server-side (it's
        // drawn, not looped), so the one visible panel's chart is drawn here
        // by reusing the existing drawLineChart() — same function the
        // dashboard's own trend charts already use, just called once instead
        // of wired to period-toggle buttons.
        const visiblePanel = document.querySelector('[data-preview-panel]:not(.hidden)');
        const trendSvg = visiblePanel?.querySelector('[data-trend-chart-static]');
        if (trendSvg) {
            const values = JSON.parse(trendSvg.dataset.values || '[]');
            drawLineChart(trendSvg, values);
        }

        previewSection.classList.remove('hidden');

        return true;
    }

    function addToRecentReports(typeLabel, typeKey, periodLabel) {
        if (!historyBody) return;

        historyTable?.classList.remove('hidden');
        historyEmpty?.classList.add('hidden');

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-2.5 pr-2 font-medium text-sand-900"></td>
            <td class="py-2.5 pr-2 text-sand-600"></td>
            <td class="py-2.5 pr-2 text-sand-600"></td>
            <td class="py-2.5 pr-2 text-sand-600"></td>
            <td class="py-2.5 pr-2 text-right">
                <div class="inline-flex items-center gap-1.5">
                    <button type="button" data-view-report class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">View</button>
                    <button type="button" data-toast-trigger data-toast-message="" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Download</button>
                </div>
            </td>
        `;

        const reportName = `${typeLabel} — ${periodLabel}`;
        const today = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        row.children[0].textContent = reportName;
        row.children[1].textContent = periodLabel;
        row.children[2].textContent = today;
        row.children[3].textContent = currentUserName;

        const viewButton = row.querySelector('[data-view-report]');
        viewButton.dataset.typeKey = typeKey;
        viewButton.dataset.range = periodLabel;
        viewButton.dataset.generatedAt = today;
        viewButton.dataset.generatedBy = currentUserName;

        row.querySelector('[data-toast-trigger]').dataset.toastMessage = `Downloading ${reportName}...`;

        historyBody.prepend(row);
    }

    generateButton?.addEventListener('click', () => {
        clearError();

        if (!form.reportValidity()) {
            return;
        }

        if (fromInput.value && toInput.value && fromInput.value > toInput.value) {
            showError('Unable to generate the report. Please check the selected date range and try again.');

            return;
        }

        const typeKey = typeSelect.value;
        const typeLabel = typeSelect.selectedOptions[0]?.textContent ?? '';
        const periodLabel = `${formatInputDate(fromInput.value)} – ${formatInputDate(toInput.value)}`;
        const today = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        setLoading(true);
        successBanner?.classList.add('hidden');

        window.setTimeout(() => {
            setLoading(false);

            const rendered = renderPreview(typeKey, periodLabel, today, currentUserName);
            if (!rendered) {
                showError('Unable to generate the report. Please check the selected filters and reporting period.');

                return;
            }

            successBanner?.classList.remove('hidden');
            addToRecentReports(typeLabel, typeKey, periodLabel);
        }, 700);
    });

    document.addEventListener('click', (e) => {
        const viewTrigger = e.target.closest('[data-view-report]');
        if (viewTrigger) {
            renderPreview(
                viewTrigger.dataset.typeKey,
                viewTrigger.dataset.range,
                viewTrigger.dataset.generatedAt,
                viewTrigger.dataset.generatedBy
            );
            document.getElementById('report-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

            return;
        }

        const scrollTrigger = e.target.closest('[data-preview-scroll]');
        if (scrollTrigger) {
            document.getElementById('report-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

            return;
        }

        const downloadTrigger = e.target.closest('[data-report-download]');
        if (downloadTrigger) {
            const kind = downloadTrigger.dataset.reportDownload === 'pdf' ? 'PDF downloaded.' : 'Excel file exported.';
            showToast(kind, 'success');

            return;
        }

        if (e.target.closest('#report-print-button')) {
            window.print();
        }
    });
}
