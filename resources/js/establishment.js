/**
 * Establishment-specific frontend interactions that don't belong in the
 * shared dashboard.js engine: the Record Arrival wizard (Enter → Review →
 * Submit → Success) and the establishment profile's image gallery
 * (add/replace/remove/set-primary previews). No backend calls — everything
 * here is a frontend-only mock, consistent with the rest of the workspace.
 */
document.addEventListener('DOMContentLoaded', () => {
    initArrivalWizard();
    initImageGallery();
    initQrActions();
});

function initQrActions() {
    document.getElementById('qr-print')?.addEventListener('click', () => window.print());

    document.getElementById('qr-download')?.addEventListener('click', (e) => {
        const svg = document.getElementById('establishment-qr-svg');
        if (!svg) return;

        const source = new XMLSerializer().serializeToString(svg);
        const blob = new Blob([source], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = e.currentTarget.dataset.qrFilename || 'qr-code.svg';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    });
}

function initArrivalWizard() {
    const wizard = document.getElementById('arrival-wizard');
    if (!wizard) return;

    const form = wizard.querySelector('form');
    const steps = ['enter', 'review', 'success'];
    const stepEls = Object.fromEntries(steps.map((s) => [s, wizard.querySelector(`[data-step="${s}"]`)]));
    const stepper = wizard.querySelectorAll('[data-stepper-item]');

    function goTo(step) {
        steps.forEach((s) => stepEls[s]?.classList.toggle('hidden', s !== step));
        stepper.forEach((el, i) => {
            const stepIndex = steps.indexOf(step);
            el.classList.toggle('text-primary-700', i <= stepIndex);
            el.classList.toggle('text-sand-400', i > stepIndex);
            el.querySelector('[data-stepper-dot]')?.classList.toggle('bg-primary-700', i <= stepIndex);
            el.querySelector('[data-stepper-dot]')?.classList.toggle('bg-sand-300', i > stepIndex);
        });
    }

    function fieldLabel(field) {
        return field.closest('[data-field]')?.querySelector('label')?.textContent.trim() ?? field.name;
    }

    function populateReview() {
        const summary = wizard.querySelector('[data-review-summary]');
        if (!summary) return;

        const rows = Array.from(form.elements)
            .filter((el) => el.name && el.type !== 'submit' && el.type !== 'button')
            .map((el) => {
                const value = el.value.trim();
                return `
                    <div class="flex items-center justify-between border-b border-sand-100 py-2 text-sm last:border-0">
                        <span class="text-sand-500">${fieldLabel(el)}</span>
                        <span class="font-medium text-sand-900">${value || '—'}</span>
                    </div>
                `;
            })
            .join('');

        summary.innerHTML = rows;
    }

    wizard.querySelector('[data-step-next]')?.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        populateReview();
        goTo('review');
    });

    wizard.querySelector('[data-step-back]')?.addEventListener('click', () => goTo('enter'));

    wizard.querySelector('[data-step-submit]')?.addEventListener('click', () => {
        goTo('success');
    });

    wizard.querySelector('[data-step-reset]')?.addEventListener('click', () => {
        form.reset();
        goTo('enter');
    });

    goTo('enter');
}

function initImageGallery() {
    const gallery = document.getElementById('image-gallery');
    if (!gallery) return;

    gallery.addEventListener('click', (e) => {
        const setPrimary = e.target.closest('[data-set-primary]');
        if (setPrimary) {
            gallery.querySelectorAll('[data-image-card]').forEach((card) => {
                const isTarget = card === setPrimary.closest('[data-image-card]');
                card.querySelector('[data-primary-badge]')?.classList.toggle('hidden', !isTarget);
                card.querySelector('[data-set-primary]')?.classList.toggle('hidden', isTarget);
            });
        }

        const remove = e.target.closest('[data-remove-image]');
        if (remove) {
            remove.closest('[data-image-card]')?.remove();
        }
    });

    const addInput = document.getElementById('image-upload-input');
    addInput?.addEventListener('change', () => {
        const file = addInput.files?.[0];
        if (!file) return;

        const url = URL.createObjectURL(file);
        const card = document.createElement('div');
        card.dataset.imageCard = '';
        card.className = 'group relative overflow-hidden rounded-md border border-sand-200';
        card.innerHTML = `
            <img src="${url}" alt="${file.name}" class="h-32 w-full object-cover">
            <div class="absolute inset-0 flex items-end justify-between bg-gradient-to-t from-sand-900/60 via-transparent to-transparent p-2 opacity-0 transition-opacity group-hover:opacity-100">
                <button type="button" data-set-primary class="rounded-sm bg-sand-0/90 px-2 py-1 text-[11px] font-semibold text-sand-800">Set as Featured</button>
                <button type="button" data-remove-image class="rounded-sm bg-danger px-2 py-1 text-[11px] font-semibold text-white">Remove</button>
            </div>
        `;
        gallery.querySelector('[data-image-grid]')?.appendChild(card);
        addInput.value = '';
    });
}
