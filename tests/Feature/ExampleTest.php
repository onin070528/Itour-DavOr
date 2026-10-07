<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — example.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */
test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
