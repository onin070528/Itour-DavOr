<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Word lists and tuning values for App\Services\SentimentAnalyzer
 * (English, Filipino and Bisaya tourist-feedback vocabulary).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

return [
    /*
    | Words are lowercase. Value = strength (1 = normal, 1.5 = strong).
    */
    'positive' => [
        // English
        'good' => 1, 'great' => 1.5, 'amazing' => 1.5, 'beautiful' => 1.5, 'awesome' => 1.5, 'excellent' => 1.5,
        'wonderful' => 1.5, 'love' => 1.5, 'loved' => 1.5, 'clean' => 1, 'friendly' => 1, 'helpful' => 1,
        'delicious' => 1.5, 'fresh' => 1, 'peaceful' => 1, 'perfect' => 1.5, 'nice' => 1, 'best' => 1.5,
        'stunning' => 1.5, 'breathtaking' => 1.5, 'relaxing' => 1, 'comfortable' => 1, 'warm' => 1, 'fun' => 1,
        'enjoy' => 1, 'enjoyed' => 1, 'worth' => 1, 'recommend' => 1, 'recommended' => 1, 'lovely' => 1.5,
        'gorgeous' => 1.5, 'quiet' => 1, 'safe' => 1, 'accommodating' => 1, 'welcoming' => 1, 'memorable' => 1,
        'unreal' => 1, 'highlight' => 1, 'happy' => 1, 'satisfied' => 1, 'superb' => 1.5, 'fantastic' => 1.5,
        'pleasant' => 1, 'tidy' => 1, 'affordable' => 1, 'organized' => 1, 'professional' => 1,
        'perfectly' => 1, 'impressive' => 1, 'refreshing' => 1, 'scenic' => 1, 'tasty' => 1,
        // Filipino
        'maganda' => 1.5, 'ganda' => 1.5, 'mabait' => 1, 'malinis' => 1, 'masarap' => 1.5, 'tahimik' => 1,
        'payapa' => 1, 'sulit' => 1, 'magaling' => 1, 'mahusay' => 1, 'maayos' => 1, 'masaya' => 1,
        'maginhawa' => 1, 'ligtas' => 1, 'salamat' => 1, 'babalik' => 1, 'kahanga-hanga' => 1.5, 'astig' => 1,
        'mura' => 1, 'matulungin' => 1, 'magalang' => 1,
        // Bisaya
        'lami' => 1.5, 'nindot' => 1.5, 'limpyo' => 1, 'malinawon' => 1, 'maayo' => 1, 'buotan' => 1,
        'ganahan' => 1, 'balikan' => 1, 'gwapo' => 1, 'hilom' => 1, 'matinabangon' => 1, 'barato' => 1,
    ],

    'negative' => [
        // English
        'bad' => 1, 'terrible' => 1.5, 'awful' => 1.5, 'dirty' => 1.5, 'rude' => 1.5, 'slow' => 1, 'late' => 1,
        'closed' => 1, 'expensive' => 1, 'overpriced' => 1.5, 'noisy' => 1, 'crowded' => 1, 'difficult' => 1,
        'worst' => 1.5, 'poor' => 1, 'disappointing' => 1.5, 'disappointed' => 1.5, 'boring' => 1, 'unsafe' => 1.5,
        'broken' => 1, 'wasted' => 1.5, 'rushed' => 1, 'smelly' => 1.5, 'potholes' => 1, 'pothole' => 1,
        'unfriendly' => 1.5, 'unhelpful' => 1.5, 'scam' => 1.5, 'overcrowded' => 1.5, 'horrible' => 1.5,
        'lacking' => 1, 'unclean' => 1.5, 'trash' => 1, 'garbage' => 1, 'mosquitoes' => 1, 'hassle' => 1,
        'confusing' => 1, 'uncomfortable' => 1, 'unfortunately' => 1, 'complain' => 1, 'complaint' => 1,
        'neglected' => 1, 'delayed' => 1, 'cancelled' => 1, 'canceled' => 1, 'lost' => 0.5, 'problem' => 1,
        // Filipino
        'pangit' => 1.5, 'madumi' => 1.5, 'marumi' => 1.5, 'mabagal' => 1, 'masama' => 1.5, 'bastos' => 1.5,
        'maingay' => 1, 'siksikan' => 1, 'delikado' => 1.5, 'sira' => 1, 'nakakadismaya' => 1.5, 'nakakainis' => 1.5,
        'kulang' => 1, 'sayang' => 1, 'mahirap' => 1, 'magulo' => 1, 'matagal' => 1, 'mabaho' => 1.5,
        // Bisaya
        'hugaw' => 1.5, 'daot' => 1.5, 'hinay' => 1, 'huot' => 1, 'lisud' => 1, 'baho' => 1.5, 'kulang-kulang' => 1,
    ],

    // Flip the score of a word that follows within the negation window.
    'negators' => [
        'not', 'no', 'never', "don't", "didn't", "wasn't", "isn't", "aren't", "weren't", "doesn't", "can't",
        "couldn't", "won't", 'without', 'hardly', 'hindi', 'di', 'wala', 'walang', 'dili', "wala'y", 'ayaw',
    ],

    // Make the next sentiment word stronger / weaker.
    'intensifiers' => [
        'very', 'so', 'really', 'extremely', 'super', 'sobrang', 'grabe', 'kaayo', 'gyud', 'talaga',
        'incredibly', 'totally', 'absolutely', 'napaka',
    ],
    'diminishers' => ['slightly', 'somewhat', 'medyo', 'bit', 'kinda', 'barely'],

    // Text after one of these counts for more than text before it.
    'contrast' => ['but', 'however', 'although', 'though', 'pero', 'ngunit', 'kaso', 'apan', 'subalit'],

    // Words that identify the language (English is the default).
    'language_markers' => [
        'Filipino' => [
            'ang', 'ng', 'mga', 'napaka', 'sobrang', 'maganda', 'hindi', 'po', 'dito', 'namin', 'kami', 'sana',
            'pero', 'ito', 'siya', 'talaga', 'masarap', 'malinis', 'tahimik', 'payapa', 'sulit', 'salamat',
            'mabait', 'ganda', 'sa', 'ay', 'na',
        ],
        'Bisaya' => [
            'kaayo', 'gyud', 'nindot', 'lami', 'dili', 'ug', 'unsa', 'nga', 'mao', 'balikan', 'gikan', 'ako',
            'mi', 'ni', 'apan', 'maayo', 'limpyo', 'hugaw', 'daot', 'buotan', 'ganahan',
        ],
    ],

    'tuning' => [
        'negation_window' => 3,
        'negation_factor' => -0.74,
        'intensifier_factor' => 1.5,
        'diminisher_factor' => 0.6,
        'before_contrast_weight' => 0.5,
        'after_contrast_weight' => 1.5,
        // polarity = sum / sqrt(sum^2 + normalization), squashed into -1..1
        'normalization' => 4,
        // How much of the final polarity comes from the written text vs the star rating.
        'text_weight' => 0.6,
        'rating_weight' => 0.4,
        // |polarity| at or above this is Positive/Negative, below it Neutral.
        'threshold' => 0.25,
    ],
];
