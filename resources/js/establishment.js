import Alpine from 'alpinejs';
import QRCode from 'qrcode';

/**
 * Establishment-specific frontend interactions that don't belong in the
 * shared dashboard.js engine: the Record Arrival page's reactive
 * Alpine.js form (see arrivalForm() below), auto-submitting the
 * Establishment Profile image gallery's hidden upload form, and rendering
 * the establishment's QR code (resources/views/establishment/qr.blade.php)
 * client-side (via the `qrcode` package). Every action here is now backed
 * by a real endpoint — see App\Http\Controllers\Establishment.
 *
 * Alpine is scoped to this bundle only (not app.js/dashboard.js, which stay
 * on the rest of the app's plain data-attribute JS convention) since Record
 * Arrival is the one page in this codebase built with it.
 */
window.Alpine = Alpine;
Alpine.data('arrivalForm', arrivalForm);
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
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

/**
 * Alpine component backing the Record Arrival page
 * (resources/views/establishment/arrivals/record.blade.php) — a two-column
 * layout with a Local/International x Male/Female x Age-group companion
 * matrix on the left and a sticky live-totals summary + submit button on
 * the right. Registered via Alpine.data() and instantiated in the view as
 * `x-data="arrivalForm(@json(...), @json(...))"`.
 *
 * The matrix/sum shape mirrors the public QR self-checkin form's Companion
 * Headcount (computeMatrixSums() in resources/js/app.js) — duplicated
 * rather than shared since app.js and establishment.js are separate Vite
 * entry bundles — and submits into the same flat
 * male/female/adults/children/seniors/local/foreign fields the backend
 * already validates, plus the new visitType field.
 */
function arrivalForm(actionUrl, defaultDate) {
    return {
        actionUrl,
        date: defaultDate,
        leadVisitorName: '',
        visitType: 'Daytour',
        submitting: false,
        guests: emptyGuestMatrix(),
        ageRows: [
            { key: 'adults', label: 'Adults (18 to 59)' },
            { key: 'children', label: 'Kids (Under 18)' },
            { key: 'seniors', label: 'Seniors (60 & above)' },
        ],

        inc(group, age, gender) {
            this.guests[group][age][gender]++;
        },

        dec(group, age, gender) {
            this.guests[group][age][gender] = Math.max(0, this.guests[group][age][gender] - 1);
        },

        sums() {
            const totals = { male: 0, female: 0, adults: 0, children: 0, seniors: 0, local: 0, foreign: 0 };
            for (const group of ['local', 'foreign']) {
                for (const age of ['adults', 'children', 'seniors']) {
                    for (const gender of ['male', 'female']) {
                        const value = this.guests[group][age][gender];
                        totals[group] += value;
                        totals[age] += value;
                        totals[gender] += value;
                    }
                }
            }
            return totals;
        },

        get localTotal() {
            return this.sums().local;
        },

        get foreignTotal() {
            return this.sums().foreign;
        },

        get totalPeople() {
            return 1 + this.localTotal + this.foreignTotal;
        },

        async submit() {
            if (!this.$refs.form.reportValidity()) return;

            this.submitting = true;

            try {
                const response = await fetch(this.actionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    body: JSON.stringify({
                        date: this.date,
                        visitorName: this.leadVisitorName,
                        visitType: this.visitType,
                        ...this.sums(),
                    }),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: 'Arrival recorded.', tone: 'success' } }));

                this.leadVisitorName = '';
                this.visitType = 'Daytour';
                this.date = defaultDate;
                this.guests = emptyGuestMatrix();
            } catch {
                window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: "Couldn't save this arrival — please try again.", tone: 'danger' } }));
            } finally {
                this.submitting = false;
            }
        },
    };
}

function emptyGuestMatrix() {
    const emptyAgeRow = () => ({ male: 0, female: 0 });

    return {
        local: { adults: emptyAgeRow(), children: emptyAgeRow(), seniors: emptyAgeRow() },
        foreign: { adults: emptyAgeRow(), children: emptyAgeRow(), seniors: emptyAgeRow() },
    };
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
