<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared Add/Edit/Archive Destination logic used by the LGU and PTO
 * directory controllers (validation and unique slug generation). Creating an
 * LGU destination goes through App\Services\AttractionRecordService.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared "Add / Edit / Archive Destination" logic behind the LGU and PTO
 * directory modals — the two forms are identical except for where the
 * municipality comes from (LGU: forced to the account's own; PTO: chosen
 * from the form's municipality select).
 */
trait ManagesDestinationListings
{
    /**
     * @return array{lst_name: string, lst_barangay: string, lst_description: ?string, lst_contact_office: ?string, lst_contact_phone: ?string}
     */
    protected function validatedDestinationFields(Request $objRequest): array
    {
        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contactOffice' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $arrData['name'],
            'barangay' => $arrData['barangay'],
            'description' => $arrData['description'] ?? null,
            'contact_office' => $arrData['contactOffice'] ?? null,
            'contact_phone' => $arrData['contactPhone'] ?? null,
        ];
    }

    protected function uniqueDestinationSlug(string $strName): string
    {
        return Listing::uniqueSlug($strName);
    }

    /**
     * The municipality select for the PTO "Add/Edit Destination" modal,
     * validated against the real municipality list.
     */
    protected function validatedMunicipality(Request $objRequest): string
    {
        return $objRequest->validate([
            'municipality' => ['required', 'string', Rule::in(collect(TourismCatalog::municipalities())->pluck('name'))],
        ])['municipality'];
    }
}
