<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates a public tourist feedback submission (Objective 4):
 * an eligible listing chosen by its public slug, the feedback text, the
 * optional name and visit date, the required consent, and the shared
 * Cloudflare Turnstile check.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Requests;

use App\Models\Listing;
use App\Rules\Turnstile;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public form, no account: authorize() is always true; eligibility is a
 * validation rule. The listing is identified only by its public slug
 * (never an internal id or the QR uuid) and must currently accept
 * feedback (Listing::scopeAcceptingFeedback()). `website` is the honeypot:
 * it is accepted here and handled by FeedbackSubmissionService. Turnstile
 * reuses App\Rules\Turnstile — the same rule, keys, and config as login.
 */
class StoreTouristFeedbackRequest extends FormRequest
{
    private ?Listing $objEligibleListing = null;

    private bool $blnIsListingResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing' => ['required', 'string', 'max:255', function (string $strAttribute, mixed $mixValue, Closure $fnFail) {
                if ($this->eligibleListing() === null) {
                    $fnFail('Please choose a destination or establishment from the list.');
                }
            }],
            'feedback' => ['required', 'string', 'min:'.(int) config('tourist_feedback.feedback_min_length'), 'max:'.(int) config('tourist_feedback.feedback_max_length')],
            'tourist_name' => ['nullable', 'string', 'max:'.(int) config('tourist_feedback.tourist_name_max_length')],
            'visit_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:255'],
            'cf-turnstile-response' => ['required', 'string', new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'listing.required' => 'Please choose the destination or establishment you visited.',
            'feedback.required' => 'Please write your feedback.',
            'feedback.min' => 'Please write at least :min characters.',
            'feedback.max' => 'Please keep your feedback to :max characters or fewer.',
            'tourist_name.max' => 'Please keep your name to :max characters or fewer.',
            'visit_date.date_format' => 'Please enter a valid visit date.',
            'visit_date.before_or_equal' => 'The visit date cannot be in the future.',
            'consent.accepted' => 'Please agree to the consent statement to submit your feedback.',
            'cf-turnstile-response.required' => 'Please complete the verification check.',
        ];
    }

    /**
     * The listing named by the submitted slug, only if it currently accepts
     * feedback; resolved once per request.
     */
    public function eligibleListing(): ?Listing
    {
        if (! $this->blnIsListingResolved) {
            $mixSlug = $this->input('listing');
            $this->objEligibleListing = is_string($mixSlug)
                ? Listing::query()->acceptingFeedback()->where('lst_slug', $mixSlug)->first()
                : null;
            $this->blnIsListingResolved = true;
        }

        return $this->objEligibleListing;
    }
}
