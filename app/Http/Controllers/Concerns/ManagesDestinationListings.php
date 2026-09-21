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
     * @return array{name: string, barangay: string, description: ?string, contactOffice: ?string, contactPhone: ?string}
     */
    protected function validatedDestinationFields(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contactOffice' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $data['name'],
            'barangay' => $data['barangay'],
            'description' => $data['description'] ?? null,
            'contact_office' => $data['contactOffice'] ?? null,
            'contact_phone' => $data['contactPhone'] ?? null,
        ];
    }

    protected function createDestination(array $fields, string $municipality): Listing
    {
        return Listing::query()->create([
            ...$fields,
            'slug' => $this->uniqueDestinationSlug($fields['name']),
            'category' => 'destinations',
            'municipality' => $municipality,
            'status' => 'Active',
        ]);
    }

    protected function uniqueDestinationSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'destination';
        $slug = $base;
        $suffix = 2;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * The municipality select for the PTO "Add/Edit Destination" modal,
     * validated against the real municipality list.
     */
    protected function validatedMunicipality(Request $request): string
    {
        return $request->validate([
            'municipality' => ['required', 'string', Rule::in(collect(\App\Support\TourismCatalog::municipalities())->pluck('name'))],
        ])['municipality'];
    }
}
