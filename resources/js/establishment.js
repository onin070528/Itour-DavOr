import Alpine from 'alpinejs';

/**
 * Establishment-specific frontend interactions that don't belong in the
 * shared dashboard.js engine: the Record Arrival page's reactive
 * Alpine.js form (see arrivalForm() below). The QR code page
 * (resources/views/establishment/qr.blade.php) needs no JS — its Download
 * and Print Poster buttons are plain links to the QrCodeController endpoints.
 *
 * Alpine is scoped to this bundle only (not app.js/dashboard.js, which stay
 * on the rest of the app's plain data-attribute JS convention) since Record
 * Arrival is the one page in this codebase built with it.
 */
window.Alpine = Alpine;
Alpine.data('arrivalForm', arrivalForm);
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initQrActions();
});

function initQrActions() {
    // The QR page shows two cards (check-in and feedback); each card's own
    // buttons act on that card only.
    document.querySelectorAll('[data-qr-card]').forEach((card) => {
        card.querySelector('[data-qr-card-print]')?.addEventListener('click', () => {
            card.classList.add('qr-print-target');
            window.print();
            card.classList.remove('qr-print-target');
        });

        card.querySelector('[data-qr-card-download]')?.addEventListener('click', (e) => {
            const svg = card.querySelector('svg');
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
        visitorContact: '',
        remarks: '',
        visitType: 'Daytour',
        localOriginScope: '',
        localOriginMunicipality: '',
        localOriginPlace: '',
        foreignCountry: '',
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
            return this.localTotal + this.foreignTotal;
        },

        // The place sent depends on the chosen scope: a Davao Oriental
        // municipality for "within", a province for "outside", else nothing.
        originPlace() {
            if (this.localTotal <= 0) return null;
            if (this.localOriginScope === 'within_province') return this.localOriginMunicipality || null;
            if (this.localOriginScope === 'outside_province') return this.localOriginPlace.trim() || null;
            return null;
        },

        // Local guests need within/outside Davao Oriental plus the
        // municipality or home province; foreign guests need their home
        // country. Returns the message, or '' when the origin is complete.
        originMissing() {
            if (this.localTotal > 0) {
                if (!this.localOriginScope) return 'Please tell us whether the local guests are from within or outside Davao Oriental.';
                if (!this.originPlace()) {
                    return this.localOriginScope === 'within_province'
                        ? 'Please choose the municipality or city the local guests are from.'
                        : 'Please enter the home province of the local guests.';
                }
            }
            if (this.foreignTotal > 0 && !this.foreignCountry.trim()) {
                return 'Please enter the home country of the foreign guests.';
            }
            return '';
        },

        async submit() {
            if (!this.$refs.form.reportValidity()) return;
            if (this.totalPeople < 1) {
                window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: 'Add at least one guest to the headcount.', tone: 'danger' } }));
                return;
            }

            // Where the group is from is required for every non-empty part of it.
            const originError = this.originMissing();
            if (originError) {
                window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: originError, tone: 'danger' } }));
                return;
            }

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
                        visitorContact: this.visitorContact,
                        remarks: this.remarks,
                        visitType: this.visitType,
                        localOriginScope: this.localTotal > 0 ? (this.localOriginScope || null) : null,
                        localOriginPlace: this.originPlace(),
                        foreignCountry: this.foreignTotal > 0 ? this.foreignCountry : null,
                        ...this.sums(),
                    }),
                });

                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                    window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: firstError || "Couldn't save this arrival — please try again.", tone: 'danger' } }));
                    return;
                }

                window.dispatchEvent(new CustomEvent('itour:toast', { detail: { message: 'Arrival recorded.', tone: 'success' } }));

                this.leadVisitorName = '';
                this.visitorContact = '';
                this.remarks = '';
                this.visitType = 'Daytour';
                this.localOriginScope = '';
                this.localOriginMunicipality = '';
                this.localOriginPlace = '';
                this.foreignCountry = '';
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

