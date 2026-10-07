<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates the PTO "Add User" form — role-conditional municipality and establishment
 * fields.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Listing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Only a PTO Administrator ever reaches this request — the route is
     * already gated by the `role:pto_administrator` middleware, but the
     * check is repeated here so this request is never silently reused
     * from a route that isn't.
     */
    public function authorize(): bool
    {
        return $this->user()?->isPto() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tbl_users', 'usr_email')],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'municipality_id' => ['required_unless:role,'.UserRole::PtoAdministrator->value, 'nullable', 'integer', 'exists:tbl_municipalities,mun_id'],
            'establishment_id' => ['required_if:role,'.UserRole::Establishment->value, 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'municipality_id.required_unless' => 'Please select a municipality.',
            'establishment_id.required_if' => 'Please select an establishment.',
        ];
    }

    /**
     * Cross-field check: the chosen establishment must actually belong
     * to the chosen municipality and must not already have a linked
     * account — both re-checked here rather than trusted from the AJAX
     * dropdown, since that list could be stale by the time of submit.
     */
    public function withValidator(Validator $objValidator): void
    {
        $objValidator->after(function (Validator $objValidator) {
            if ($this->input('role') !== UserRole::Establishment->value || $this->input('establishment_id') === null) {
                return;
            }

            $objListing = Listing::query()
                ->where('lst_id', $this->input('establishment_id'))
                ->where('mun_id', $this->input('municipality_id'))
                ->where('lst_category', '!=', 'destinations')
                ->whereDoesntHave('establishmentUser')
                ->first();

            if ($objListing === null) {
                $objValidator->errors()->add('establishment_id', 'Please select an available establishment in the chosen municipality.');
            }
        });
    }
}
