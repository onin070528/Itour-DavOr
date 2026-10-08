<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: A short, presentation-only excerpt of a longer text (for example
 * a listing's description on an Explore card), cut at a sentence boundary.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Not a generated summary: the excerpt is the text's own leading
 * sentences, unchanged. It keeps whole sentences up to the sentence and
 * word limits; when even the first sentence is too long it is cut at a word
 * boundary and ends with an ellipsis. Any HTML tags are removed and the
 * whitespace is collapsed, so the result is plain text (still escaped by
 * Blade when displayed). The stored text is never changed.
 */
class TextSummary
{
    /** Most whole sentences an excerpt keeps. */
    public const MAX_SENTENCES = 2;

    /** Most words an excerpt keeps. */
    public const MAX_WORDS = 35;

    /** Appended when a sentence had to be cut. */
    public const ELLIPSIS = '…';

    /**
     * The leading sentences of $strText within the limits, or an empty
     * string when there is no text.
     */
    public static function excerpt(?string $strText, int $intMaxWords = self::MAX_WORDS, int $intMaxSentences = self::MAX_SENTENCES): string
    {
        // Summary comment: drop script/style blocks with their contents, then any other tags.
        $strWithoutBlocks = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#isu', ' ', (string) $strText);
        $strPlain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($strWithoutBlocks)));

        if ($strPlain === '') {
            return '';
        }

        // Summary comment: split after ., !, or ? followed by a space — a
        // simple, explainable sentence boundary.
        $arrSentences = preg_split('/(?<=[.!?])\s+/u', $strPlain) ?: [$strPlain];
        $arrKept = [];
        $intWords = 0;

        foreach ($arrSentences as $strSentence) {
            // Words are counted by whitespace, the same way Str::words() cuts.
            $intSentenceWords = count(preg_split('/\s+/u', $strSentence) ?: []);
            $blnIsFull = count($arrKept) >= $intMaxSentences || $intWords + $intSentenceWords > $intMaxWords;

            if ($blnIsFull) {
                break;
            }

            $arrKept[] = $strSentence;
            $intWords += $intSentenceWords;
        } // end foreach sentence

        // Summary comment: even the first sentence is too long — cut it at a word boundary.
        if ($arrKept === []) {
            return rtrim(Str::words($arrSentences[0], $intMaxWords, ''), ' ,;:—-').self::ELLIPSIS;
        }

        return implode(' ', $arrKept);
    }
}
