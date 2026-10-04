/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : PTO Add/Edit User modal — role-dependent fields, AJAX establishment
 *              loading, and the one-time account-created confirmation panel.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

const ROLE_PTO = 'pto_administrator';
const ROLE_ESTABLISHMENT = 'establishment';

document.addEventListener('DOMContentLoaded', function ()
{
    initUserAccountForm();
    initAccountCreatedPanel();
});

/**
 * Wires up the Add/Edit User modal: the Municipality field hides for
 * PTO Administrator, and the Establishment field only shows for Tourism
 * Establishment, loading its options via AJAX for the selected
 * municipality.
 */
function initUserAccountForm()
{
    var objForm = document.getElementById('user-form');

    if (!objForm)
    {
        return;
    }

    var objRoleSelect = objForm.elements.namedItem('role');
    var objMunicipalityField = document.getElementById('user-form-municipality-field');
    var objMunicipalitySelect = objForm.elements.namedItem('municipality_id');
    var objEstablishmentField = document.getElementById('user-form-establishment-field');
    var objEstablishmentSelect = objForm.elements.namedItem('establishment_id');
    var strEstablishmentsUrl = objForm.dataset.establishmentsUrl;

    if (!objRoleSelect || !objMunicipalityField || !objMunicipalitySelect || !objEstablishmentField || !objEstablishmentSelect)
    {
        return;
    }

    /**
     * Shows/hides Municipality and Establishment for the currently
     * selected role, and makes Municipality required only while visible.
     */
    function updateFieldVisibility()
    {
        var strRole = objRoleSelect.value;
        var blnShowMunicipality = strRole !== ROLE_PTO;
        var blnShowEstablishment = strRole === ROLE_ESTABLISHMENT;

        objMunicipalityField.hidden = !blnShowMunicipality;
        objMunicipalitySelect.required = blnShowMunicipality;
        objEstablishmentField.hidden = !blnShowEstablishment;
        objEstablishmentSelect.required = blnShowEstablishment;

        if (!blnShowEstablishment)
        {
            resetEstablishmentOptions('Select a municipality first');
        }
    }

    /**
     * Resets the Establishment select to a single disabled placeholder
     * option carrying the given label.
     */
    function resetEstablishmentOptions(strPlaceholder)
    {
        objEstablishmentSelect.innerHTML = '';

        var objPlaceholder = document.createElement('option');
        objPlaceholder.value = '';
        objPlaceholder.textContent = strPlaceholder;
        objPlaceholder.disabled = true;
        objPlaceholder.selected = true;
        objEstablishmentSelect.appendChild(objPlaceholder);
    }

    /**
     * Fills the Establishment select with the given rows, pre-selecting
     * strPreselectId when it's among them (used when pre-filling Edit).
     * Falls back to the "no unassigned establishments" placeholder when
     * the list is empty.
     */
    function fillEstablishmentOptions(arrEstablishments, strPreselectId)
    {
        if (arrEstablishments.length === 0)
        {
            resetEstablishmentOptions('No unassigned establishments');
            return;
        }

        objEstablishmentSelect.innerHTML = '';

        var objPlaceholder = document.createElement('option');
        objPlaceholder.value = '';
        objPlaceholder.textContent = 'Select an establishment';
        objPlaceholder.disabled = true;
        objPlaceholder.selected = true;
        objEstablishmentSelect.appendChild(objPlaceholder);

        arrEstablishments.forEach(function (objEstablishment)
        {
            var objOption = document.createElement('option');
            objOption.value = String(objEstablishment.id);
            objOption.textContent = objEstablishment.name;

            if (strPreselectId && String(objEstablishment.id) === String(strPreselectId))
            {
                objOption.selected = true;
                objPlaceholder.selected = false;
            }

            objEstablishmentSelect.appendChild(objOption);
        });
    }

    /**
     * Fetches the unassigned establishments for the selected
     * municipality. strPreselectId, when given, is selected once the
     * options load — used when pre-filling the Edit form.
     */
    function loadEstablishments(strPreselectId)
    {
        if (objRoleSelect.value !== ROLE_ESTABLISHMENT)
        {
            return;
        }

        var strMunicipalityId = objMunicipalitySelect.value;

        if (!strMunicipalityId)
        {
            resetEstablishmentOptions('Select a municipality first');
            return;
        }

        resetEstablishmentOptions('Loading…');

        var objUrl = new URL(strEstablishmentsUrl, window.location.origin);
        objUrl.searchParams.set('municipality_id', strMunicipalityId);

        fetch(objUrl, { headers: { Accept: 'application/json' } })
            .then(function (objResponse)
            {
                if (!objResponse.ok)
                {
                    throw new Error('Request failed');
                }

                return objResponse.json();
            })
            .then(function (objData)
            {
                fillEstablishmentOptions(objData.establishments || [], strPreselectId);
            })
            .catch(function ()
            {
                resetEstablishmentOptions('Could not load establishments');
            });
    }

    objRoleSelect.addEventListener('change', function ()
    {
        updateFieldVisibility();
        loadEstablishments(null);
    });

    objMunicipalitySelect.addEventListener('change', function ()
    {
        loadEstablishments(null);
    });

    // Opening the modal fresh (Add) resets the fields to the PTO default.
    document.querySelectorAll('[data-modal-open="user-form-modal"]:not([data-edit-trigger])').forEach(function (objTrigger)
    {
        objTrigger.addEventListener('click', function ()
        {
            updateFieldVisibility();
            resetEstablishmentOptions('Select a municipality first');
        });
    });

    // Editing a row: dashboard.js's own edit-trigger listener (registered
    // first, since dashboard.js loads before this file) has already
    // copied role/municipality_id/establishment_id into the fields by the
    // time this listener runs — this one only has to reveal the right
    // fields and, for an Establishment account, load and preselect its
    // establishment (its option can't exist yet, since that list is
    // always AJAX-loaded).
    document.querySelectorAll('[data-edit-trigger="user-form-modal"]').forEach(function (objTrigger)
    {
        objTrigger.addEventListener('click', function ()
        {
            var objValues = JSON.parse(objTrigger.dataset.editValues || '{}');

            updateFieldVisibility();

            if (objValues.role === ROLE_ESTABLISHMENT && objValues.establishment_id)
            {
                loadEstablishments(String(objValues.establishment_id));
            }
        });
    });

    updateFieldVisibility();
}

/**
 * The one-time "account created" confirmation panel — opened
 * automatically when the page was reloaded with one to show (a flashed,
 * server-rendered value; see resources/views/pto/users.blade.php). Wires
 * up its copy-to-clipboard button. The passphrase is never read from
 * anywhere but this panel's own DOM text — not a cookie, not
 * localStorage — and disappears for good on the next page load.
 */
function initAccountCreatedPanel()
{
    var objPanel = document.getElementById('account-created-modal');

    if (!objPanel)
    {
        return;
    }

    objPanel.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    var objCopyButton = document.getElementById('account-created-copy');
    var objPassphraseText = document.getElementById('account-created-passphrase');

    objCopyButton?.addEventListener('click', function ()
    {
        navigator.clipboard.writeText(objPassphraseText.textContent.trim()).then(function ()
        {
            var strOriginalLabel = objCopyButton.textContent;
            objCopyButton.textContent = 'Copied!';
            window.setTimeout(function ()
            {
                objCopyButton.textContent = strOriginalLabel;
            }, 1500);
        });
    });

    var objResendButton = document.getElementById('account-created-resend');

    objResendButton?.addEventListener('click', function ()
    {
        var objStatus = document.getElementById('account-created-email-status');
        var strCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        objResendButton.disabled = true;

        fetch(objResendButton.dataset.resendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': strCsrfToken,
            },
            body: JSON.stringify({
                user_id: objResendButton.dataset.userId,
                passphrase: objPassphraseText.textContent.trim(),
            }),
        })
            .then(function (objResponse)
            {
                return objResponse.json().then(function (objData)
                {
                    return { ok: objResponse.ok, data: objData };
                });
            })
            .then(function (objResult)
            {
                if (objResult.ok && objResult.data.sent)
                {
                    objStatus.textContent = 'Welcome email sent.';
                    objStatus.className = 'rounded-sm px-3 py-2 text-xs bg-success-bg text-success';
                    objResendButton.hidden = true;
                    return;
                }

                throw new Error('Send failed');
            })
            .catch(function ()
            {
                objStatus.textContent = 'Email could not be sent. Please give the temporary password to the user directly.';
                objStatus.className = 'rounded-sm px-3 py-2 text-xs bg-warning-bg text-warning';
                objResendButton.disabled = false;
            });
    });
}
