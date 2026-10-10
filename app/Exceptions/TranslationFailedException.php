<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Raised when tourist feedback cannot be translated into English
 * (provider not configured, request failed, or an invalid, empty, or
 * implausible response). The message is a short, fixed reason that is
 * safe to store in tbl_feedbacks.fbk_failure_reason: it never contains
 * the feedback text, the provider response, or any credential.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Exceptions;

use RuntimeException;

class TranslationFailedException extends RuntimeException
{
    public const NOT_CONFIGURED = 'Translation provider is not configured.';

    public const REQUEST_FAILED = 'Translation request failed.';

    public const INVALID_RESPONSE = 'Translation response was invalid.';

    public const EMPTY_TRANSLATION = 'Translation was empty.';

    public const IMPLAUSIBLE_LENGTH = 'Translation length was implausible.';
}
