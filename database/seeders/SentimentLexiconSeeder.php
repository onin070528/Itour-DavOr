<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the tourism-oriented sentiment lexicon
 * (tbl_sentiment_lexicons) used by the lexicon-based polarity scoring.
 * Idempotent: re-running updates words in place and never duplicates them.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\SentimentLexicon;
use Illuminate\Database\Seeder;

/**
 * A focused tourism vocabulary, not a generic dictionary. Every weight is
 * 1 so the score follows the documented count-based formula
 * S = (P - N) / T. Neutral describing words ("view", "place", "staff",
 * "very", "maintained") are deliberately absent: they must not change P or
 * N in the documented examples. Negation ("not good") is handled by the
 * analysis service, not by lexicon entries.
 */
class SentimentLexiconSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const POSITIVE_WORDS = [
        // Required starting set (Objective 4, R6).
        'beautiful', 'clean', 'amazing', 'excellent', 'good', 'great', 'friendly',
        'peaceful', 'comfortable', 'wonderful', 'nice', 'enjoyable', 'relaxing',
        'scenic', 'breathtaking', 'accessible', 'helpful', 'safe',
        // Tourism additions.
        'awesome', 'fantastic', 'lovely', 'pristine', 'affordable', 'welcoming',
        'hospitable', 'delicious', 'convenient', 'spacious', 'memorable', 'refreshing',
        'organized', 'courteous', 'calm', 'stunning', 'perfect', 'fun',
    ];

    /**
     * @var array<int, string>
     */
    private const NEGATIVE_WORDS = [
        // Required starting set (Objective 4, R6). 'poorly' makes the
        // documented example "Bad experience and poorly maintained" N = 2.
        'bad', 'dirty', 'crowded', 'expensive', 'poor', 'poorly', 'unsafe', 'rude',
        'unfriendly', 'broken', 'noisy', 'disappointing', 'uncomfortable',
        'inconvenient', 'difficult', 'overpriced', 'unmaintained',
        // Tourism additions.
        'terrible', 'horrible', 'awful', 'filthy', 'smelly', 'unsanitary', 'unhelpful',
        'dangerous', 'damaged', 'polluted', 'overcrowded', 'worst', 'boring',
        'disorganized', 'inaccessible', 'unprofessional', 'slippery',
    ];

    public function run(): void
    {
        $this->_seedWords(self::POSITIVE_WORDS, SentimentLexicon::POLARITY_POSITIVE);
        $this->_seedWords(self::NEGATIVE_WORDS, SentimentLexicon::POLARITY_NEGATIVE);

        // Model events already clear the cache per row; this also covers a
        // run with model events muted.
        SentimentLexicon::clearCachedLexicon();
    }

    /**
     * Inserts or updates each word with the given polarity and weight 1.
     *
     * @param  array<int, string>  $arrWords
     */
    private function _seedWords(array $arrWords, string $strPolarity): void
    {
        foreach ($arrWords as $strWord) {
            SentimentLexicon::query()->updateOrCreate(
                ['slx_word' => $strWord],
                ['slx_polarity' => $strPolarity, 'slx_weight' => 1]
            );
        }
    }
}
