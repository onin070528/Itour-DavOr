<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared Add/Edit/Archive Destination logic used by the LGU and PTO
 * directory controllers (validation, creation, and unique slug generation).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'lst_name' => $arrData['name'],
            'lst_barangay' => $arrData['barangay'],
            'lst_description' => $arrData['description'] ?? null,
            'lst_contact_office' => $arrData['contactOffice'] ?? null,
            'lst_contact_phone' => $arrData['contactPhone'] ?? null,
        ];
    }

    /**
     * $intMunicipalityId is the real FK (App\Models\Municipality) — the caller
     * resolves it (LGU: its own account's mun_id, already a
     * reliable FK; PTO: looked up from the submitted municipality name)
     * since only the caller knows which is trustworthy for its form.
     * Left null here, it's null on the row too, which then fails the
     * municipality-scoped access checks (Lgu\DirectoryController::
     * authorizeOwnMunicipality) — always pass it.
     */
    protected function createDestination(array $arrFields, string $strMunicipality, ?int $intMunicipalityId): Listing
    {
        return Listing::query()->create([
            ...$arrFields,
            'lst_slug' => $this->uniqueDestinationSlug($arrFields['lst_name']),
            'lst_category' => 'destinations',
            'lst_municipality' => $strMunicipality,
            'mun_id' => $intMunicipalityId,
            'lst_status' => 'Active',
        ]);
    }

    protected function uniqueDestinationSlug(string $strName): string
    {
        $strBase = Str::slug($strName) ?: 'destination';
        $strSlug = $strBase;
        $intSuffix = 2;

        while (Listing::query()->where('lst_slug', $strSlug)->exists()) {
            $strSlug = "{$strBase}-{$intSuffix}";
            $intSuffix++;
        }

        return $strSlug;
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
