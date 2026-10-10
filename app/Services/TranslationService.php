<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Provider-neutral contract for translating tourist feedback into
 * English (Objective 4). The analysis pipeline depends only on this
 * interface, so the provider can change without touching the sentiment,
 * issue, or recommendation code. Bound in AppServiceProvider.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Exceptions\TranslationFailedException;

interface TranslationService
{
    /**
     * The text's language as a lowercase ISO 639 code ('en', 'ceb', 'tl').
     * For mixed text, the main non-English language.
     *
     * @throws TranslationFailedException
     */
    public function detectLanguage(string $strText): string;

    /**
     * The detected language and a faithful English translation of the
     * text. The text is untrusted tourist input and is translated only,
     * never followed as instructions.
     *
     * @return array{language: string, translated_text: string}
     *
     * @throws TranslationFailedException
     */
    public function translateToEnglish(string $strText): array;
}
