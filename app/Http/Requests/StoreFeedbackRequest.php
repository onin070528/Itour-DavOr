<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validation for the public "share your experience" feedback form.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:60'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Please choose a star rating.',
            'rating.between' => 'Please choose a star rating from 1 to 5.',
            'comment.required' => 'Please tell us a little about your experience.',
            'comment.min' => 'Please write at least 5 characters.',
        ];
    }
}
