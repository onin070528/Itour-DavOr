<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Validates that a submitted establishment type belongs to the submitted category.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server-side half of the dependent Category -> Type dropdown: the browser
 * only filters the options for convenience, this rule is the authority.
 * The allowed list comes from Category::establishmentTypes() (backed by
 * config/establishment_categories.php), so the form, the filters, and this
 * rule can never disagree. Callers decide whether the type is required —
 * see self::rulesFor().
 */
class EstablishmentTypeBelongsToCategory implements ValidationRule
{
    public function __construct(private readonly ?Category $objCategory) {}

    /**
     * The full rule list for a `type` field given the submitted category:
     * required (and limited to that category's types) for an establishment
     * category, prohibited for Tourist Destinations, which have no types.
     * An unknown category leaves `type` nullable — the category field's
     * own `exists` rule reports that error instead.
     *
     * @return array<int, mixed>
     */
    public static function rulesFor(?Category $objCategory): array
    {
        if ($objCategory === null) {
            return ['nullable', 'string'];
        }

        if ($objCategory->isDestinationCategory()) {
            return ['prohibited'];
        }

        return ['required', 'string', new self($objCategory)];
    } // end rulesFor

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $arrAllowedTypes = $this->objCategory?->establishmentTypes() ?? [];
        $blnIsAllowed = is_string($value) && in_array($value, $arrAllowedTypes, true);

        if (! $blnIsAllowed) {
            $fail('Please choose an establishment type that belongs to the selected category.');
        }
    }
}
