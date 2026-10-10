<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Configuration — services.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mapbox' => [
        'token' => env('MAPBOX_SECRET_KEY'),
    ],

    // Objective 4: used ONLY to translate tourist feedback into English
    // (App\Services\OpenAiTranslationService). Sentiment, issue detection,
    // and recommendations never call it.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => 'https://api.openai.com/v1',
        'translation_model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4.1-mini'),
    ],

    // Objective 4: the free-tier alternative translation provider, used only
    // when TRANSLATION_PROVIDER=gemini (App\Services\GeminiTranslationService).
    // Same single purpose as 'openai' above: tourist feedback into English.
    // The Flash-Lite alias answers in about a second; the full Flash models
    // can take over a minute, longer than the translation timeout.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'translation_model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-flash-lite-latest'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
