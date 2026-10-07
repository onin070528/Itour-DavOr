<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Server-side verification of a Cloudflare Turnstile response token
 * against Cloudflare's siteverify endpoint, as a normal Laravel validation
 * rule on the login form.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates the `cf-turnstile-response` token the Turnstile widget submits
 * alongside the login form. The secret key never reaches the browser — it's
 * only ever used here, server-side, to call Cloudflare's siteverify API.
 */
class Turnstile implements ValidationRule
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private readonly ?string $remoteIp = null) {}

    public function validate(string $strAttribute, mixed $value, Closure $fnFail): void
    {
        if (! is_string($value) || $value === '') {
            $fnFail('Please complete the verification check.');

            return;
        }

        try {
            $objResponse = Http::asForm()->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => $this->remoteIp,
            ]);

            if (! $objResponse->successful() || ! ($objResponse->json('success') === true)) {
                Log::warning('Turnstile verification failed.', [
                    'status' => $objResponse->status(),
                    'response' => $objResponse->json(),
                ]);

                $fnFail('Verification check failed. Please try again.');
            }
        } catch (\Throwable $objException) {
            Log::error('Turnstile verification request failed.', ['exception' => $objException]);

            $fnFail('Verification check failed. Please try again.');
        }
    }
}
