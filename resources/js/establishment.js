import QRCode from 'qrcode';

/**
 * Establishment-specific frontend interactions that don't belong in the
 * shared dashboard.js engine: the Record Arrival wizard's fetch-based final
 * submit step (Enter → Review → Submit → Success), auto-submitting the
 * Establishment Profile image gallery's hidden upload form, and rendering
 * the establishment's QR code (resources/views/establishment/qr.blade.php)
 * client-side (via the `qrcode` package). Every action here is now backed
 * by a real endpoint — see App\Http\Controllers\Establishment.
 */
document.addEventListener('DOMContentLoaded', () => {
    initArrivalWizard();
    initImageGallery();
    initQrActions();
});

function initQrActions() {
    renderEstablishmentQr();

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

/**
 * Renders a real, scannable SVG QR code into #establishment-qr-mount,
 * encoding that element's `data-qr-value` — this establishment's unique
 * check-in URL (routes/web.php's /checkin/{establishment}). Replaces the
 * old decorative placeholder pattern, which only ever looked like a QR
 * code and could never actually be scanned.
 */
function renderEstablishmentQr() {
    const mount = document.getElementById('establishment-qr-mount');
    const value = mount?.dataset.qrValue;
    if (!mount || !value) return;

    QRCode.toString(value, { type: 'svg', margin: 1, width: 256 })
        .then((svg) => {
            mount.innerHTML = svg;
            const svgEl = mount.querySelector('svg');
            svgEl?.setAttribute('id', 'establishment-qr-svg');
            svgEl?.classList.add('h-full', 'w-full');
        })
        .catch(() => {
            mount.innerHTML = '<span class="text-xs text-danger">Couldn\'t generate QR code.</span>';
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

    const submitButton = wizard.querySelector('[data-step-submit]');
    submitButton?.addEventListener('click', async () => {
        submitButton.disabled = true;

        try {
            const response = await fetch(submitButton.dataset.actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify(Object.fromEntries(new FormData(form))),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            goTo('success');
        } catch {
            window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: "Couldn't save this arrival — please try again.", tone: 'danger' } }));
        } finally {
            submitButton.disabled = false;
        }
    });

    wizard.querySelector('[data-step-reset]')?.addEventListener('click', () => {
        form.reset();
        goTo('enter');
    });

    goTo('enter');
}

/**
 * Set-as-Featured, Remove, and Add Image are each a real per-photo
 * `<form>` now (resources/views/establishment/profile.blade.php) — Set-
 * as-Featured and Remove submit like any other form (Remove goes through
 * the shared confirm-dialog flow in dashboard.js first), so the only
 * gallery-specific behavior left here is auto-submitting the hidden Add
 * Image form the moment a file is chosen.
 */
function initImageGallery() {
    const uploadForm = document.getElementById('image-upload-form');
    const addInput = document.getElementById('image-upload-input');

    addInput?.addEventListener('change', () => {
        if (addInput.files?.length) {
            uploadForm.submit();
        }
    });
}
