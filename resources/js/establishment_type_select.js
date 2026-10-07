/**
 * Dependent Category -> Establishment Type dropdown for
 * <x-dashboard.establishment-type-select>. Every type option carries the
 * cat_id it belongs to (`data-category-id`); only the selected category's
 * options stay selectable, and a type that no longer belongs to the chosen
 * category is cleared. Convenience only — the server always re-validates
 * (App\Rules\EstablishmentTypeBelongsToCategory).
 */

/**
 * Shows only `typeSelect`'s options for `categoryId`, clearing the current
 * value if it belongs to another category. Safe to call after a form is
 * filled programmatically (setting .value never fires `change`).
 *
 * @param {HTMLSelectElement} typeSelect
 * @param {string} categoryId
 */
export function syncEstablishmentTypeOptions(typeSelect, categoryId) {
    let hasMatchingOption = false;

    typeSelect.querySelectorAll('option[data-category-id]').forEach((option) => {
        const isMatch = option.dataset.categoryId === String(categoryId);
        option.hidden = !isMatch;
        option.disabled = !isMatch;
        hasMatchingOption = hasMatchingOption || isMatch;
    });

    const selectedOption = typeSelect.selectedOptions[0];
    const isSelectedStale = selectedOption?.dataset.categoryId !== undefined && selectedOption.dataset.categoryId !== String(categoryId);

    if (isSelectedStale) {
        typeSelect.value = '';
    }

    // A select the server rendered as locked (`data-locked`) stays disabled.
    if (typeSelect.dataset.locked === undefined) {
        typeSelect.disabled = !hasMatchingOption;
    }
}

/**
 * Whether `typeSelect`'s current value is the list-only tour guide type
 * (config/establishment_categories.php 'tour_guide_type').
 *
 * @param {HTMLSelectElement|null} typeSelect
 * @returns {boolean}
 */
export function isTourGuideTypeSelected(typeSelect) {
    if (!typeSelect) return false;

    return typeSelect.value !== '' && typeSelect.value === typeSelect.dataset.tourGuideType;
}

/**
 * Wires every `[data-establishment-type-select]` on the page to its form's
 * Category select (`data-category-field`, default cat_id).
 */
export function initEstablishmentTypeSelects() {
    document.querySelectorAll('[data-establishment-type-select]').forEach((typeSelect) => {
        const categorySelect = typeSelect.form?.elements.namedItem(typeSelect.dataset.categoryField || 'cat_id');
        if (!categorySelect) return;

        const sync = () => syncEstablishmentTypeOptions(typeSelect, categorySelect.value);

        categorySelect.addEventListener('change', sync);
        sync();
    });
}
